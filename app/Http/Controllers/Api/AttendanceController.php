<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    /**
     * Clock in/out endpoint
     */
    public function clockInOut(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_address' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee data not found'
                ], 404);
            }

            // Check if employee is active
            if (!$employee->is_active) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee account is inactive'
                ], 403);
            }

            $today = Carbon::today();
            $now = Carbon::now();

            // Check if today is holiday
            if (Holiday::isHoliday($today, $employee->company_id)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot clock in/out on holiday'
                ], 400);
            }

            // Check work schedule
            $workSchedule = $employee->workSchedule;
            if (!$workSchedule || !$workSchedule->is_active) {
                return response()->json([
                    'status' => false,
                    'message' => 'Work schedule not found or inactive'
                ], 400);
            }

            // Check if today is work day
            $dayOfWeek = $now->dayOfWeek;
            if ($dayOfWeek == 0) $dayOfWeek = 7; // Sunday = 7
            
            if (!$workSchedule->isWorkDay($dayOfWeek)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot clock in/out on non-working day'
                ], 400);
            }

            // Check office location
            $office = $employee->office;
            if (!$office || !$office->is_active) {
                return response()->json([
                    'status' => false,
                    'message' => 'Office not found or inactive'
                ], 400);
            }

            if (!$office->isWithinRadius($request->latitude, $request->longitude)) {
                return response()->json([
                    'status' => false,
                    'message' => "You are outside office radius ({$office->radius}m). Please move closer to the office."
                ], 400);
            }

            // Get today's attendance
            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('date', $today)
                ->first();

            if (!$attendance) {
                // Clock In
                $result = $this->performClockIn($employee, $office, $workSchedule, $request, $now);
            } else {
                // Clock Out
                if ($attendance->clock_out) {
                    return response()->json([
                        'status' => false,
                        'message' => 'You have already clocked out today'
                    ], 400);
                }
                $result = $this->performClockOut($employee, $attendance, $request, $now);
            }

            DB::commit();
            return response()->json($result);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance status for today (for button enable/disable)
     */
   public function getAttendanceStatus(Request $request): JsonResponse
    {
        try {
            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee data not found'
                ], 404);
            }

            $today = Carbon::today();
            $now = Carbon::now();

            $canClockIn = false;
            $canClockOut = false;
            $message = '';

            // Check if employee is active
            if (!$employee->is_active) {
                return response()->json([
                    'status' => true,
                    'data' => [
                        'can_clock_in' => false,
                        'can_clock_out' => false,
                        'message' => 'Employee account is inactive'
                    ]
                ]);
            }

            // Check if today is holiday
            if (Holiday::isHoliday($today, $employee->company_id)) {
                return response()->json([
                    'status' => true,
                    'data' => [
                        'can_clock_in' => false,
                        'can_clock_out' => false,
                        'message' => 'Today is holiday'
                    ]
                ]);
            }

            // Check work schedule
            $workSchedule = $employee->workSchedule;
            if (!$workSchedule || !$workSchedule->is_active) {
                return response()->json([
                    'status' => true,
                    'data' => [
                        'can_clock_in' => false,
                        'can_clock_out' => false,
                        'message' => 'Work schedule not found or inactive'
                    ]
                ]);
            }

            // Check if today is work day
            $dayOfWeek = $now->dayOfWeek;
            if ($dayOfWeek == 0) $dayOfWeek = 7; // Sunday = 7
            
            if (!$workSchedule->isWorkDay($dayOfWeek)) {
                return response()->json([
                    'status' => true,
                    'data' => [
                        'can_clock_in' => false,
                        'can_clock_out' => false,
                        'message' => 'Today is not a working day'
                    ]
                ]);
            }

            // Check office (without location validation)
            $office = $employee->office;
            if (!$office || !$office->is_active) {
                return response()->json([
                    'status' => true,
                    'data' => [
                        'can_clock_in' => false,
                        'can_clock_out' => false,
                        'message' => 'Office not found or inactive'
                    ]
                ]);
            }

            // Get today's attendance
            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('date', $today)
                ->first();

            if (!$attendance) {
                // Can clock in
                $canClockIn = true;
                $message = 'Ready to clock in';
            } else {
                if ($attendance->clock_out) {
                    // Already completed attendance
                    $message = 'Attendance completed for today';
                } else {
                    // Can clock out
                    $canClockOut = true;
                    $message = 'Ready to clock out';
                }
            }

            // ===== TAMBAHAN UNTUK OVERTIME INFO =====
            
            // Get overtime requests for today
            $approvedOvertime = OvertimeRequest::where('employee_id', $employee->id)
                ->whereDate('date', $today)
                ->where('status', 'approved')
                ->first();

            $pendingOvertime = OvertimeRequest::where('employee_id', $employee->id)
                ->whereDate('date', $today)
                ->where('status', 'pending')
                ->first();

            // Check if can request overtime today
            $canRequestOvertime = $this->canRequestOvertimeToday($employee, $today);

            // ===== END OVERTIME INFO =====

            return response()->json([
                'status' => true,
                'data' => [
                    'can_clock_in' => $canClockIn,
                    'can_clock_out' => $canClockOut,
                    'message' => $message,
                    'employee_name' => $employee->name,
                    'office_name' => $office->name,
                    'today_attendance' => $attendance ? [
                        'clock_in' => $attendance->clock_in,
                        'clock_out' => $attendance->clock_out,
                        'status' => $attendance->status,
                    ] : null,
                    
                    // ===== OVERTIME INFO DITAMBAHKAN =====
                    'overtime_info' => [
                        'has_approved_overtime' => $approvedOvertime ? true : false,
                        'approved_overtime' => $approvedOvertime ? [
                            'id' => $approvedOvertime->id,
                            'start_time' => $approvedOvertime->getFormattedStartTime(),
                            'end_time' => $approvedOvertime->getFormattedEndTime(),
                            'duration' => $approvedOvertime->getFormattedDuration(),
                            'reason' => $approvedOvertime->reason,
                        ] : null,
                        'has_pending_overtime' => $pendingOvertime ? true : false,
                        'pending_overtime' => $pendingOvertime ? [
                            'id' => $pendingOvertime->id,
                            'start_time' => $pendingOvertime->getFormattedStartTime(),
                            'end_time' => $pendingOvertime->getFormattedEndTime(),
                            'duration' => $pendingOvertime->getFormattedDuration(),
                            'reason' => $pendingOvertime->reason,
                        ] : null,
                        'can_request_overtime' => $canRequestOvertime,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

/**
 * Check if employee can request overtime for today
 */
    private function canRequestOvertimeToday($employee, $date)
    {
        // Check if there's already a request for today
        $existingRequest = OvertimeRequest::where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($existingRequest) {
            return false;
        }

        // Check if today is a working day (already checked above, but just to be safe)
        $workSchedule = $employee->workSchedule;
        if (!$workSchedule || !$workSchedule->is_active) {
            return false;
        }

        $dayOfWeek = $date->dayOfWeek;
        if ($dayOfWeek == 0) $dayOfWeek = 7; // Sunday = 7

        return $workSchedule->isWorkDay($dayOfWeek);
    }


    /**
     * Perform clock in
     */
    private function performClockIn($employee, $office, $workSchedule, $request, $now)
    {
        // Create attendance record
        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'office_id' => $office->id,
            'date' => $now->toDateString(),
            'clock_in' => $now->toTimeString(),
            'clock_in_lat' => $request->latitude,
            'clock_in_lng' => $request->longitude,
            'clock_in_address' => $request->location_address,
            'status' => 'present',
        ]);

        // Check if late
        $scheduledStart = Carbon::parse($workSchedule->start_time);
        $isLate = $now->gt($scheduledStart);
        
        if ($isLate) {
            $attendance->update(['status' => 'late']);
        }

        // Log activity - with error handling
        try {
            $activityLog = ActivityLog::logClockIn(
                $employee,
                $attendance,
                $request->latitude,
                $request->longitude,
                $request->location_address
            );
            
            \Log::info('Clock In Activity Log Created', [
                'activity_log_id' => $activityLog->id,
                'employee_id' => $employee->id,
                'attendance_id' => $attendance->id
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create Clock In Activity Log', [
                'error' => $e->getMessage(),
                'employee_id' => $employee->id,
                'attendance_id' => $attendance->id
            ]);
            // Don't fail the whole process, just log the error
        }

        return [
            'status' => true,
            'message' => 'Clock in successful',
            'action' => 'clock_in',
            'data' => [
                'attendance_id' => $attendance->id,
                'clock_in_time' => $attendance->clock_in,
                'is_late' => $isLate,
                'status' => $attendance->status,
                'location' => $request->location_address,
            ]
        ];
    }

    /**
     * Perform clock out
     */
    private function performClockOut($employee, $attendance, $request, $now)
    {
        // Update attendance record
        $attendance->update([
            'clock_out' => $now->toTimeString(),
            'clock_out_lat' => $request->latitude,
            'clock_out_lng' => $request->longitude,
            'clock_out_address' => $request->location_address,
        ]);

        // Refresh attendance data after update
        $attendance->refresh();

        // Calculate durations with proper error handling
        try {
            $durations = $this->calculateDurations($attendance, $employee->workSchedule);
            
            \Log::info('Duration calculation result', [
                'attendance_id' => $attendance->id,
                'durations' => $durations
            ]);

            // Update attendance with calculated durations
            $attendance->update($durations);
            
            // Refresh to get updated values
            $attendance->refresh();
            
            \Log::info('Attendance updated with durations', [
                'attendance_id' => $attendance->id,
                'work_duration' => $attendance->work_duration,
                'overtime_duration' => $attendance->overtime_duration
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to calculate work duration', [
                'error' => $e->getMessage(),
                'attendance_id' => $attendance->id,
                'clock_in' => $attendance->clock_in,
                'clock_out' => $attendance->clock_out,
            ]);
            
            // Set to 0 instead of null for consistency
            $attendance->update([
                'work_duration' => 0,
                'overtime_duration' => 0,
            ]);
        }

        // Get the final values for response
        $workDuration = $attendance->work_duration ?? 0;
        $overtimeDuration = $attendance->overtime_duration ?? 0;

        // Log activity - with error handling
        try {
            $activityLog = ActivityLog::logClockOut(
                $employee,
                $attendance,
                $request->latitude,
                $request->longitude,
                $request->location_address
            );
            
            \Log::info('Clock Out Activity Log Created', [
                'activity_log_id' => $activityLog->id,
                'employee_id' => $employee->id,
                'attendance_id' => $attendance->id
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to create Clock Out Activity Log', [
                'error' => $e->getMessage(),
                'employee_id' => $employee->id,
                'attendance_id' => $attendance->id
            ]);
            // Don't fail the whole process, just log the error
        }

        return [
            'status' => true,
            'message' => 'Clock out successful',
            'action' => 'clock_out',
            'data' => [
                'attendance_id' => $attendance->id,
                'clock_out_time' => $attendance->clock_out,
                'work_duration' => $this->formatDuration($attendance->work_duration),
                'overtime_duration' => $this->formatDuration($attendance->overtime_duration),
                'location' => $request->location_address,
            ]
        ];
    }

    /**
     * Calculate work and overtime durations - FIXED FOR NEGATIVE VALUES
     */
   private function calculateDurations($attendance, $workSchedule)
    {
        if (!$attendance->clock_in || !$attendance->clock_out) {
            \Log::warning('Missing clock in/out times', [
                'attendance_id' => $attendance->id,
                'clock_in' => $attendance->clock_in,
                'clock_out' => $attendance->clock_out
            ]);
            return [
                'work_duration' => 0,
                'overtime_duration' => 0,
            ];
        }

        try {
            // Get date string properly
            $dateString = $attendance->date instanceof Carbon ? 
                $attendance->date->format('Y-m-d') : 
                Carbon::parse($attendance->date)->format('Y-m-d');
            
            \Log::info('Calculating durations', [
                'attendance_id' => $attendance->id,
                'date_string' => $dateString,
                'clock_in_raw' => $attendance->clock_in,
                'clock_out_raw' => $attendance->clock_out
            ]);
            
            // Parse times correctly
            $clockInDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateString . ' ' . $attendance->clock_in);
            $clockOutDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateString . ' ' . $attendance->clock_out);
            
            \Log::info('Parsed datetimes', [
                'clock_in_datetime' => $clockInDateTime->toDateTimeString(),
                'clock_out_datetime' => $clockOutDateTime->toDateTimeString()
            ]);
            
            // Handle cross-day scenario
            if ($clockOutDateTime->lte($clockInDateTime)) {
                \Log::warning('Clock out is before or equal to clock in', [
                    'attendance_id' => $attendance->id,
                    'clock_in' => $clockInDateTime->toDateTimeString(),
                    'clock_out' => $clockOutDateTime->toDateTimeString()
                ]);
                
                if ($clockOutDateTime->lt($clockInDateTime)) {
                    $clockOutDateTime->addDay();
                    \Log::info('Adjusted clock out to next day', [
                        'new_clock_out' => $clockOutDateTime->toDateTimeString()
                    ]);
                }
            }
            
            // Calculate total work duration in minutes
            $totalWorkMinutes = $clockOutDateTime->diffInMinutes($clockInDateTime);
            $totalWorkMinutes = abs($totalWorkMinutes);
            
            \Log::info('Total work duration calculated', [
                'total_work_minutes' => $totalWorkMinutes
            ]);
            
            $result = [
                'work_duration' => $totalWorkMinutes,
                'overtime_duration' => 0,
            ];
            
            // Calculate overtime with approved overtime request integration
            if ($workSchedule && $workSchedule->end_time) {
                try {
                    $scheduledEndDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateString . ' ' . $workSchedule->end_time);
                    
                    // Check if there's an approved overtime request for this date
                    $approvedOvertime = OvertimeRequest::where('employee_id', $attendance->employee_id)
                        ->whereDate('date', $dateString)
                        ->where('status', 'approved')
                        ->first();
                    
                    if ($approvedOvertime) {
                        \Log::info('Found approved overtime request', [
                            'overtime_id' => $approvedOvertime->id,
                            'approved_start' => $approvedOvertime->start_time,
                            'approved_end' => $approvedOvertime->end_time,
                            'approved_duration' => $approvedOvertime->duration
                        ]);
                        
                        // Use approved overtime end time as the limit
                        $approvedOvertimeEndTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateString . ' ' . $approvedOvertime->end_time);
                        
                        if ($clockOutDateTime->gt($scheduledEndDateTime)) {
                            // Calculate overtime duration, but cap it to approved overtime
                            $actualOvertimeMinutes = $clockOutDateTime->diffInMinutes($scheduledEndDateTime);
                            $maxAllowedOvertimeMinutes = $approvedOvertime->duration;
                            
                            // Use the minimum of actual overtime or approved overtime
                            $result['overtime_duration'] = min($actualOvertimeMinutes, $maxAllowedOvertimeMinutes);
                            
                            \Log::info('Overtime calculated with approval', [
                                'scheduled_end' => $scheduledEndDateTime->toTimeString(),
                                'actual_out' => $clockOutDateTime->toTimeString(),
                                'actual_overtime_minutes' => $actualOvertimeMinutes,
                                'approved_overtime_minutes' => $maxAllowedOvertimeMinutes,
                                'final_overtime_minutes' => $result['overtime_duration']
                            ]);
                            
                            // If employee worked beyond approved overtime, show warning
                            if ($actualOvertimeMinutes > $maxAllowedOvertimeMinutes) {
                                \Log::warning('Employee worked beyond approved overtime', [
                                    'attendance_id' => $attendance->id,
                                    'excess_minutes' => $actualOvertimeMinutes - $maxAllowedOvertimeMinutes
                                ]);
                                
                                // You could store this excess for reporting/review
                                // For now, we just cap it to approved duration
                            }
                        }
                    } else {
                        // No approved overtime request - handle regular overtime calculation
                        if ($clockOutDateTime->gt($scheduledEndDateTime)) {
                            $overtimeMinutes = $clockOutDateTime->diffInMinutes($scheduledEndDateTime);
                            
                            // You may want to cap this or set it to 0 if no approval exists
                            // For now, we'll allow minimal overtime (e.g., 15 minutes grace period)
                            $gracePeriodinutes = 15;
                            
                            if ($overtimeMinutes > $gracePeriodMinutes) {
                                // Only count overtime beyond grace period, or set to 0 if no approval required
                                $result['overtime_duration'] = 0; // Or ($overtimeMinutes - $gracePeriodMinutes)
                                
                                \Log::info('Overtime worked without approval', [
                                    'scheduled_end' => $scheduledEndDateTime->toTimeString(),
                                    'actual_out' => $clockOutDateTime->toTimeString(),
                                    'overtime_minutes' => $overtimeMinutes,
                                    'counted_overtime' => $result['overtime_duration']
                                ]);
                            }
                        }
                    }
                    
                } catch (\Exception $e) {
                    \Log::warning('Failed to calculate overtime with approval check', [
                        'error' => $e->getMessage(),
                        'work_schedule_end_time' => $workSchedule->end_time
                    ]);
                    
                    // Fallback to basic calculation
                    if ($clockOutDateTime->gt($scheduledEndDateTime)) {
                        $overtimeMinutes = $clockOutDateTime->diffInMinutes($scheduledEndDateTime);
                        $result['overtime_duration'] = abs($overtimeMinutes);
                    }
                }
            }
            
            \Log::info('Final duration result with overtime integration', $result);
            return $result;
            
        } catch (\Exception $e) {
            \Log::error('Error calculating durations with overtime integration', [
                'error' => $e->getMessage(),
                'attendance_id' => $attendance->id,
                'date' => $attendance->date,
                'clock_in' => $attendance->clock_in,
                'clock_out' => $attendance->clock_out,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'work_duration' => 0,
                'overtime_duration' => 0,
            ];
        }
    }

    /**
     * Convert minutes to HH:MM format for display - FIXED FOR NEGATIVE VALUES
     */
    private function formatDuration($minutes)
    {
        if ($minutes === null) {
            return '00:00';
        }
        
        // Handle negative values - just show as positive
        $absMinutes = abs($minutes);
        
        if ($absMinutes === 0) {
            return '00:00';
        }
        
        $hours = floor($absMinutes / 60);
        $mins = $absMinutes % 60;
        
        // Add negative sign if original was negative (for debugging purposes)
        $sign = $minutes < 0 ? '-' : '';
        
        return $sign . sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * Get activity logs
     */
    public function getActivityLogs(Request $request): JsonResponse
    {
        try {
            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee data not found'
                ], 404);
            }

            $period = $request->get('period', 'today'); // today, week, month
            $type = $request->get('type'); // clock_in, clock_out, etc

            $query = ActivityLog::where('employee_id', $employee->id);

            switch ($period) {
                case 'week':
                    $query->thisWeek();
                    break;
                case 'month':
                    $query->thisMonth();
                    break;
                default:
                    $query->today();
                    break;
            }

            if ($type) {
                $query->byType($type);
            }

            $activities = $query->orderBy('activity_time', 'desc')
                ->paginate(20);

            return response()->json([
                'status' => true,
                'data' => $activities
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}