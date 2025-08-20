<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Visit;
use App\Models\Attendance;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VisitController extends Controller
{
    /**
     * Request visit (employee creates visit request)
     */
    public function requestVisit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'visit_type' => 'required|string|in:client_visit,site_inspection,meeting,delivery,other',
            'purpose' => 'required|string|max:500',
            'location_name' => 'required|string|max:255',
            'client_name' => 'nullable|string|max:255',
            'planned_start_time' => 'required|date|after_or_equal:today',
            'planned_end_time' => 'required|date|after:planned_start_time',
            'notes' => 'nullable|string|max:1000',
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

            // Check if employee has approver
            if (!$employee->approver_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'No approver assigned to your account'
                ], 400);
            }

            $visit = Visit::create([
                'employee_id' => $employee->id,
                'visit_type' => $request->visit_type,
                'purpose' => $request->purpose,
                'location_name' => $request->location_name,
                'client_name' => $request->client_name,
                'planned_start_time' => $request->planned_start_time,
                'planned_end_time' => $request->planned_end_time,
                'notes' => $request->notes,
                'status' => 'pending',
            ]);

            // Log activity - FIXED: Added required fields
            ActivityLog::create([
                'employee_id' => $employee->id,
                'company_id' => $employee->company_id,
                'activity_type' => 'visit_requested',
                'title' => 'Visit Request Created',
                'description' => "Visit request created: {$visit->purpose}",
                'activity_time' => now(),
                'metadata' => json_encode([
                    'visit_id' => $visit->id,
                    'visit_type' => $visit->visit_type,
                    'location' => $visit->location_name,
                    'planned_time' => $visit->getFormattedPlannedStartTime() . ' - ' . $visit->getFormattedPlannedEndTime()
                ])
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Visit request submitted successfully',
                'data' => [
                    'visit_id' => $visit->id,
                    'status' => $visit->status,
                    'visit_type' => $visit->visit_type,
                    'purpose' => $visit->purpose,
                    'location_name' => $visit->location_name,
                    'planned_start_time' => $visit->getFormattedPlannedStartTime(),
                    'planned_end_time' => $visit->getFormattedPlannedEndTime(),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start visit (clock in via visit)
     */
    public function startVisit(Request $request, $visitId): JsonResponse
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
            $visit = Visit::where('id', $visitId)
                         ->where('employee_id', $employee->id)
                         ->first();

            if (!$visit) {
                return response()->json([
                    'status' => false,
                    'message' => 'Visit not found'
                ], 404);
            }

            if (!$visit->canStartVisit()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Visit cannot be started. Status: ' . $visit->status
                ], 400);
            }

            $now = Carbon::now();
            $today = $now->toDateString();

            // Check if already has attendance today
            $attendance = Attendance::where('employee_id', $employee->id)
                                   ->whereDate('date', $today)
                                   ->first();

            if ($attendance && $attendance->clock_in) {
                return response()->json([
                    'status' => false,
                    'message' => 'You have already clocked in today'
                ], 400);
            }

            // Create or update attendance record
            if (!$attendance) {
                $attendance = Attendance::create([
                    'employee_id' => $employee->id,
                    'office_id' => $employee->office_id,
                    'date' => $today,
                    'clock_in' => $now->toTimeString(),
                    'clock_in_lat' => $request->latitude,
                    'clock_in_lng' => $request->longitude,
                    'clock_in_address' => $request->location_address,
                    'status' => 'present',
                ]);
            } else {
                $attendance->update([
                    'clock_in' => $now->toTimeString(),
                    'clock_in_lat' => $request->latitude,
                    'clock_in_lng' => $request->longitude,
                    'clock_in_address' => $request->location_address,
                    'status' => 'present',
                ]);
            }

            // Update visit
            $visit->update([
                'attendance_id' => $attendance->id,
                'actual_start_time' => $now,
                'start_lat' => $request->latitude,
                'start_lng' => $request->longitude,
                'start_address' => $request->location_address,
                'status' => 'in_progress',
            ]);

            // Log activity - FIXED: Added required fields
            ActivityLog::create([
                'employee_id' => $employee->id,
                'company_id' => $employee->company_id,
                'activity_type' => 'visit_started',
                'title' => 'Visit Started',
                'description' => "Visit started: {$visit->purpose}",
                'activity_time' => $now,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'location_address' => $request->location_address,
                'metadata' => json_encode([
                    'visit_id' => $visit->id,
                    'attendance_id' => $attendance->id,
                    'visit_type' => $visit->visit_type,
                    'location' => $visit->location_name
                ])
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Visit started successfully (Clock In)',
                'action' => 'visit_start',
                'data' => [
                    'visit_id' => $visit->id,
                    'attendance_id' => $attendance->id,
                    'start_time' => $visit->getFormattedActualStartTime(),
                    'location' => $request->location_address,
                    'purpose' => $visit->purpose,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * End visit (clock out via visit)
     */
    public function endVisit(Request $request, $visitId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_address' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
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
            $visit = Visit::where('id', $visitId)
                         ->where('employee_id', $employee->id)
                         ->first();

            if (!$visit) {
                return response()->json([
                    'status' => false,
                    'message' => 'Visit not found'
                ], 404);
            }

            if (!$visit->canEndVisit()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Visit cannot be ended. Current status: ' . $visit->status
                ], 400);
            }

            $now = Carbon::now();
            $attendance = $visit->attendance;

            if (!$attendance) {
                return response()->json([
                    'status' => false,
                    'message' => 'Attendance record not found'
                ], 404);
            }

            // Update attendance record
            $attendance->update([
                'clock_out' => $now->toTimeString(),
                'clock_out_lat' => $request->latitude,
                'clock_out_lng' => $request->longitude,
                'clock_out_address' => $request->location_address,
            ]);

            // Calculate durations
            $attendance->refresh();
            try {
                $durations = $this->calculateVisitDurations($attendance, $employee->workSchedule);
                $attendance->update($durations);
                $attendance->refresh();
            } catch (\Exception $e) {
                \Log::error('Failed to calculate visit duration', [
                    'error' => $e->getMessage(),
                    'attendance_id' => $attendance->id,
                ]);
                $attendance->update([
                    'work_duration' => 0,
                    'overtime_duration' => 0,
                ]);
            }

            // Update visit
            $visit->update([
                'actual_end_time' => $now,
                'end_lat' => $request->latitude,
                'end_lng' => $request->longitude,
                'end_address' => $request->location_address,
                'status' => 'completed',
                'notes' => $request->notes ? $visit->notes . "\n\nEnd Notes: " . $request->notes : $visit->notes,
            ]);

            // Log activity - FIXED: Added required fields
            ActivityLog::create([
                'employee_id' => $employee->id,
                'company_id' => $employee->company_id,
                'activity_type' => 'visit_ended',
                'title' => 'Visit Completed',
                'description' => "Visit completed: {$visit->purpose}",
                'activity_time' => $now,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'location_address' => $request->location_address,
                'metadata' => json_encode([
                    'visit_id' => $visit->id,
                    'attendance_id' => $attendance->id,
                    'duration' => $visit->getFormattedDuration(),
                    'work_duration' => $this->formatDuration($attendance->work_duration),
                ])
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Visit ended successfully (Clock Out)',
                'action' => 'visit_end',
                'data' => [
                    'visit_id' => $visit->id,
                    'attendance_id' => $attendance->id,
                    'end_time' => $visit->getFormattedActualEndTime(),
                    'duration' => $visit->getFormattedDuration(),
                    'work_duration' => $this->formatDuration($attendance->work_duration),
                    'location' => $request->location_address,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee's visits
     */
    public function getMyVisits(Request $request): JsonResponse
    {
        try {
            $employee = $request->user()->employee;
            $status = $request->get('status'); // pending, approved, rejected, in_progress, completed
            $period = $request->get('period', 'month'); // today, week, month

            $query = Visit::where('employee_id', $employee->id);

            if ($status) {
                $query->where('status', $status);
            }

            switch ($period) {
                case 'today':
                    $query->today();
                    break;
                case 'week':
                    $query->thisWeek();
                    break;
                default:
                    $query->thisMonth();
                    break;
            }

            $visits = $query->with(['approver:id,name'])
                          ->orderBy('planned_start_time', 'desc')
                          ->paginate(20);

            return response()->json([
                'status' => true,
                'data' => $visits
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate work duration for visit
     */
    private function calculateVisitDurations($attendance, $workSchedule)
    {
        if (!$attendance->clock_in || !$attendance->clock_out) {
            return [
                'work_duration' => 0,
                'overtime_duration' => 0,
            ];
        }

        try {
            $dateString = $attendance->date instanceof Carbon ? 
                $attendance->date->format('Y-m-d') : 
                Carbon::parse($attendance->date)->format('Y-m-d');
            
            $clockInDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateString . ' ' . $attendance->clock_in);
            $clockOutDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateString . ' ' . $attendance->clock_out);
            
            if ($clockOutDateTime->lte($clockInDateTime)) {
                $clockOutDateTime->addDay();
            }
            
            $totalWorkMinutes = $clockOutDateTime->diffInMinutes($clockInDateTime);
            
            $result = [
                'work_duration' => $totalWorkMinutes,
                'overtime_duration' => 0,
            ];

            // Calculate overtime if work schedule exists
            if ($workSchedule && $workSchedule->end_time) {
                $scheduledEndDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateString . ' ' . $workSchedule->end_time);
                
                if ($clockOutDateTime->gt($scheduledEndDateTime)) {
                    $result['overtime_duration'] = $clockOutDateTime->diffInMinutes($scheduledEndDateTime);
                }
            }
            
            return $result;
            
        } catch (\Exception $e) {
            \Log::error('Error calculating visit durations', [
                'error' => $e->getMessage(),
                'attendance_id' => $attendance->id,
            ]);
            
            return [
                'work_duration' => 0,
                'overtime_duration' => 0,
            ];
        }
    }

    /**
     * Format duration to HH:MM
     */
    private function formatDuration($minutes)
    {
        if ($minutes === null || $minutes === 0) {
            return '00:00';
        }
        
        $absMinutes = abs($minutes);
        $hours = floor($absMinutes / 60);
        $mins = $absMinutes % 60;
        
        return sprintf('%02d:%02d', $hours, $mins);
    }
}