<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OvertimeApprovalController extends Controller
{
    /**
     * Get pending overtime requests (for approvers)
     */
     public function getPendingRequests(Request $request): JsonResponse
    {
        try {
            $employee = $request->user()->employee;
            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee data not found'
                ], 404);
            }

            // Check if user has approver role (all roles except employee)
            $user = $request->user();
            $userRole = $user->employee->role->slug ?? null;
            $allowedRoles = ['super-admin', 'company-admin', 'hr-manager', 'manager'];
            
            if (!in_array($userRole, $allowedRoles)) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to approve overtime requests'
                ], 403);
            }

            $period = $request->get('period', 'all');
            $employeeId = $request->get('employee_id');

            // Get overtime requests where current employee is the approver
            $query = OvertimeRequest::with(['employee'])
                ->where('status', 'pending')
                ->where('approved_by', $employee->id);

            // Filter by specific employee if requested
            if ($employeeId) {
                $query->where('employee_id', $employeeId);
            }

            // Filter by period
            switch ($period) {
                case 'today':
                    $query->whereDate('created_at', Carbon::today());
                    break;
                case 'week':
                    $query->whereBetween('created_at', [
                        Carbon::now()->startOfWeek(),
                        Carbon::now()->endOfWeek()
                    ]);
                    break;
                case 'month':
                    $query->whereBetween('created_at', [
                        Carbon::now()->startOfMonth(),
                        Carbon::now()->endOfMonth()
                    ]);
                    break;
            }

            // Get all results without pagination
            $overtimeRequests = $query->orderBy('created_at', 'asc')->get();

            return response()->json([
                'status' => true,
                'data' => $overtimeRequests,
                'total' => $overtimeRequests->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Approve overtime request
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:500',
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

            $approver = $request->user()->employee;
            if (!$approver) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee data not found'
                ], 404);
            }

            $overtimeRequest = OvertimeRequest::with(['employee'])->find($id);
            if (!$overtimeRequest) {
                return response()->json([
                    'status' => false,
                    'message' => 'Overtime request not found'
                ], 404);
            }

            // Check if request is still pending
            if ($overtimeRequest->status !== 'pending') {
                return response()->json([
                    'status' => false,
                    'message' => 'This request has already been processed'
                ], 400);
            }

            // Check if current user is the assigned approver for this request
            if ($overtimeRequest->approved_by !== $approver->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to approve this request'
                ], 403);
            }

            // Approve the request
            $overtimeRequest->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            // Log activity for approver
            try {
                ActivityLog::create([
                    'employee_id' => $approver->id,
                    'activity_type' => 'overtime_approval',
                    'activity_time' => now(),
                    'description' => "Approved overtime request for {$overtimeRequest->employee->name} on {$overtimeRequest->date->format('d M Y')}",
                    'metadata' => json_encode([
                        'overtime_request_id' => $overtimeRequest->id,
                        'employee_name' => $overtimeRequest->employee->name,
                        'date' => $overtimeRequest->date->format('Y-m-d'),
                        'duration' => $overtimeRequest->duration,
                        'notes' => $request->notes,
                    ])
                ]);

                // Log activity for employee (notification)
                ActivityLog::create([
                    'employee_id' => $overtimeRequest->employee_id,
                    'activity_type' => 'overtime_approved',
                    'activity_time' => now(),
                    'description' => "Your overtime request for {$overtimeRequest->date->format('d M Y')} has been approved by {$approver->name}",
                    'metadata' => json_encode([
                        'overtime_request_id' => $overtimeRequest->id,
                        'approver_name' => $approver->name,
                        'date' => $overtimeRequest->date->format('Y-m-d'),
                        'duration' => $overtimeRequest->duration,
                        'notes' => $request->notes,
                    ])
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to log overtime approval activity: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Overtime request approved successfully',
                'data' => [
                    'id' => $overtimeRequest->id,
                    'employee_name' => $overtimeRequest->employee->name,
                    'date' => $overtimeRequest->date->format('d M Y'),
                    'duration' => $overtimeRequest->getFormattedDuration(),
                    'approved_by' => $approver->name,
                    'approved_at' => now()->format('d M Y H:i'),
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
     * Reject overtime request
     */
    public function reject(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500',
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

            $approver = $request->user()->employee;
            if (!$approver) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee data not found'
                ], 404);
            }

            $overtimeRequest = OvertimeRequest::with(['employee'])->find($id);
            if (!$overtimeRequest) {
                return response()->json([
                    'status' => false,
                    'message' => 'Overtime request not found'
                ], 404);
            }

            // Check if request is still pending
            if ($overtimeRequest->status !== 'pending') {
                return response()->json([
                    'status' => false,
                    'message' => 'This request has already been processed'
                ], 400);
            }

            // Check if current user is the assigned approver for this request
            if ($overtimeRequest->approved_by !== $approver->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to reject this request'
                ], 403);
            }

            // Reject the request
            $overtimeRequest->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'approved_at' => now(),
            ]);

            // Log activity for approver
            try {
                ActivityLog::create([
                    'employee_id' => $approver->id,
                    'activity_type' => 'overtime_rejection',
                    'activity_time' => now(),
                    'description' => "Rejected overtime request for {$overtimeRequest->employee->name} on {$overtimeRequest->date->format('d M Y')}",
                    'metadata' => json_encode([
                        'overtime_request_id' => $overtimeRequest->id,
                        'employee_name' => $overtimeRequest->employee->name,
                        'date' => $overtimeRequest->date->format('Y-m-d'),
                        'duration' => $overtimeRequest->duration,
                        'rejection_reason' => $request->rejection_reason,
                    ])
                ]);

                // Log activity for employee (notification)
                ActivityLog::create([
                    'employee_id' => $overtimeRequest->employee_id,
                    'activity_type' => 'overtime_rejected',
                    'activity_time' => now(),
                    'description' => "Your overtime request for {$overtimeRequest->date->format('d M Y')} has been rejected by {$approver->name}",
                    'metadata' => json_encode([
                        'overtime_request_id' => $overtimeRequest->id,
                        'approver_name' => $approver->name,
                        'date' => $overtimeRequest->date->format('Y-m-d'),
                        'duration' => $overtimeRequest->duration,
                        'rejection_reason' => $request->rejection_reason,
                    ])
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to log overtime rejection activity: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Overtime request rejected successfully',
                'data' => [
                    'id' => $overtimeRequest->id,
                    'employee_name' => $overtimeRequest->employee->name,
                    'date' => $overtimeRequest->date->format('d M Y'),
                    'rejection_reason' => $request->rejection_reason,
                    'rejected_by' => $approver->name,
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


}