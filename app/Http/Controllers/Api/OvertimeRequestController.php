<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OvertimeRequestController extends Controller
{
    /**
     * Get overtime requests list (employee's own requests)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee data not found'
                ], 404);
            }

            $status = $request->get('status'); // pending, approved, rejected
            $period = $request->get('period', 'month'); // today, week, month, all

            $query = OvertimeRequest::where('employee_id', $employee->id)
                ->with(['approver:id,name']);

            // Filter by status
            if ($status && in_array($status, ['pending', 'approved', 'rejected'])) {
                $query->where('status', $status);
            }

            // Filter by period
            switch ($period) {
                case 'today':
                    $query->today();
                    break;
                case 'week':
                    $query->thisWeek();
                    break;
                case 'month':
                    $query->thisMonth();
                    break;
                // 'all' tidak perlu filter tambahan
            }

            $overtimeRequests = $query->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($request) {
                    $request->approver_name = $request->approver ? $request->approver->name : null;
                    unset($request->approver);
                    return $request;
                });

            return response()->json([
                'status' => true,
                'data' => $overtimeRequests
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit overtime request
     */
    public function store(Request $request): JsonResponse
    {
        // FIXED: Remove 'after:start_time' validation for cross-day overtime support
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'reason' => 'required|string|max:1000',
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

            $date = Carbon::parse($request->date);
            $startTime = $request->start_time . ':00'; // Add seconds
            $endTime = $request->end_time . ':00';

            // DEBUG: Log the values for debugging
            \Log::info('Overtime Debug:', [
                'date' => $date->format('Y-m-d'),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'raw_start' => $request->start_time,
                'raw_end' => $request->end_time
            ]);

            // FIXED: Simplified duration calculation
            try {
                $startDateTime = Carbon::parse($date->format('Y-m-d') . ' ' . $startTime);
                $endDateTime = Carbon::parse($date->format('Y-m-d') . ' ' . $endTime);
                
                // Handle cross-day overtime (end_time <= start_time means next day)
                if ($endDateTime->lte($startDateTime)) {
                    $endDateTime->addDay();
                }
                
                // FIXED: Use absolute difference and ensure positive result
                $duration = abs($endDateTime->diffInMinutes($startDateTime, false));
                
                \Log::info('Duration Calculation:', [
                    'start_datetime' => $startDateTime->format('Y-m-d H:i:s'),
                    'end_datetime' => $endDateTime->format('Y-m-d H:i:s'),
                    'duration_minutes' => $duration,
                    'is_cross_day' => $endDateTime->format('Y-m-d') !== $startDateTime->format('Y-m-d')
                ]);
                
            } catch (\Exception $e) {
                \Log::error('Duration calculation error: ' . $e->getMessage());
                return response()->json([
                    'status' => false,
                    'message' => 'Error calculating duration: ' . $e->getMessage()
                ], 400);
            }

            // Validation: Ensure positive duration
            if ($duration <= 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid time range. Duration calculated: ' . $duration . ' minutes'
                ], 400);
            }

            // Business rules validation
            
            // 1. Check if there's already a pending/approved request for the same date
            $existingRequest = OvertimeRequest::where('employee_id', $employee->id)
                ->whereDate('date', $date)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'status' => false,
                    'message' => 'You already have an overtime request for this date'
                ], 400);
            }

            // 2. Check if the date is too far in the future (max 7 days)
            if ($date->gt(Carbon::today()->addDays(7))) {
                return response()->json([
                    'status' => false,
                    'message' => 'Overtime request cannot be made more than 7 days in advance'
                ], 400);
            }

            // 3. Check work schedule - overtime must be after work hours
            $workSchedule = $employee->workSchedule;
            if ($workSchedule) {
                $workEndTime = Carbon::parse($workSchedule->end_time);
                $requestStartTime = Carbon::parse($startTime);
                
                // FIXED: Better work hours validation for cross-day scenarios
                if ($requestStartTime->format('H:i') < $workEndTime->format('H:i') && 
                    $requestStartTime->format('H:i') > '06:00') { // Assume work doesn't start before 6 AM
                    return response()->json([
                        'status' => false,
                        'message' => "Overtime can only start after work hours ({$workSchedule->end_time})"
                    ], 400);
                }
            }

            // 4. Check maximum overtime duration (e.g., 4 hours max)
            if ($duration > 240) { // 4 hours = 240 minutes
                return response()->json([
                    'status' => false,
                    'message' => 'Maximum overtime duration is 4 hours'
                ], 400);
            }

            // 5. Check minimum overtime duration (e.g., 30 minutes minimum)
            if ($duration < 30) {
                return response()->json([
                    'status' => false,
                    'message' => 'Minimum overtime duration is 30 minutes'
                ], 400);
            }

            // Get approver from employee's approver_id
            $approverId = $employee->approver_id;
            if (!$approverId) {
                return response()->json([
                    'status' => false,
                    'message' => 'No approver assigned for your account. Please contact HR.'
                ], 400);
            }

            // Verify approver exists and is active
            $approver = Employee::where('id', $approverId)->where('is_active', true)->first();
            if (!$approver) {
                return response()->json([
                    'status' => false,
                    'message' => 'Assigned approver is not available. Please contact HR.'
                ], 400);
            }

            // Create overtime request
            $overtimeRequest = OvertimeRequest::create([
                'employee_id' => $employee->id,
                'date' => $date->format('Y-m-d'),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration' => $duration, // Set duration explicitly
                'reason' => $request->reason,
                'status' => 'pending',
                'approved_by' => $approverId, // Set approver from employee's approver_id
            ]);

            // Log activity
            try {
                ActivityLog::create([
                    'employee_id' => $employee->id,
                    'company_id' => $employee->company_id ?? 1, // FIXED: Add company_id with fallback
                    'activity_type' => 'overtime_request',
                    'activity_time' => now(),
                    'description' => "Submitted overtime request for {$date->format('d M Y')} ({$overtimeRequest->getFormattedDuration()})",
                    'metadata' => json_encode([
                        'overtime_request_id' => $overtimeRequest->id,
                        'date' => $date->format('Y-m-d'),
                        'duration' => $overtimeRequest->duration,
                        'approver_name' => $approver->name,
                    ])
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to log overtime request activity: ' . $e->getMessage());
                // Don't fail the whole process
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Overtime request submitted successfully',
                'data' => [
                    'id' => $overtimeRequest->id,
                    'date' => $overtimeRequest->date->format('d M Y'),
                    'start_time' => $overtimeRequest->getFormattedStartTime(),
                    'end_time' => $overtimeRequest->getFormattedEndTime(),
                    'duration' => round($overtimeRequest->duration / 60, 2),
                    'reason' => $overtimeRequest->reason,
                    'status' => $overtimeRequest->status,
                    'approver' => [
                        'name' => $approver->name,
                        'position' => $approver->position,
                    ]
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
     * Get specific overtime request
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee data not found'
                ], 404);
            }

            $overtimeRequest = OvertimeRequest::where('id', $id)
                ->where('employee_id', $employee->id)
                ->with(['approver'])
                ->first();

            if (!$overtimeRequest) {
                return response()->json([
                    'status' => false,
                    'message' => 'Overtime request not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $overtimeRequest->id,
                    'date' => $overtimeRequest->date->format('d M Y'),
                    'start_time' => $overtimeRequest->getFormattedStartTime(),
                    'end_time' => $overtimeRequest->getFormattedEndTime(),
                    'duration' => [
                        'minutes' => $overtimeRequest->duration,
                        'formatted' => $overtimeRequest->getFormattedDuration(),
                        'hours' => round($overtimeRequest->duration / 60, 2)
                    ],
                    'reason' => $overtimeRequest->reason,
                    'status' => $overtimeRequest->status,
                    'rejection_reason' => $overtimeRequest->rejection_reason,
                    'approver' => $overtimeRequest->approver ? [
                        'name' => $overtimeRequest->approver->name,
                        'position' => $overtimeRequest->approver->position,
                    ] : null,
                    'approved_at' => $overtimeRequest->approved_at?->format('d M Y H:i'),
                    'created_at' => $overtimeRequest->created_at->format('d M Y H:i'),
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
     * Cancel pending overtime request
     */
    public function cancel(Request $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee data not found'
                ], 404);
            }

            $overtimeRequest = OvertimeRequest::where('id', $id)
                ->where('employee_id', $employee->id)
                ->first();

            if (!$overtimeRequest) {
                return response()->json([
                    'status' => false,
                    'message' => 'Overtime request not found'
                ], 404);
            }

            // Can only cancel pending requests
            if ($overtimeRequest->status !== 'pending') {
                return response()->json([
                    'status' => false,
                    'message' => 'Only pending requests can be cancelled'
                ], 400);
            }

            // Update status to rejected (cancelled by user)
            $overtimeRequest->update([
                'status' => 'rejected',
                'rejection_reason' => 'Cancelled by employee',
            ]);

            // Log activity
            try {
                ActivityLog::create([
                    'employee_id' => $employee->id,
                    'company_id' => $employee->company_id ?? 1, // FIXED: Add company_id with fallback
                    'activity_type' => 'overtime_cancel',
                    'activity_time' => now(),
                    'description' => "Cancelled overtime request for {$overtimeRequest->date->format('d M Y')}",
                    'metadata' => json_encode([
                        'overtime_request_id' => $overtimeRequest->id,
                        'date' => $overtimeRequest->date->format('Y-m-d'),
                    ])
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to log overtime cancel activity: ' . $e->getMessage());
                // Don't fail the whole process
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Overtime request cancelled successfully'
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
     * Helper function to format minutes to HH:MM
     */
    private function formatMinutesToHours($totalMinutes)
    {
        if (!$totalMinutes || $totalMinutes <= 0) return '00:00';
        
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        
        return sprintf('%02d:%02d', $hours, $minutes);
    }
}