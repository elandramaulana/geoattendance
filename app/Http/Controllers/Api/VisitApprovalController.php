<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Visit;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VisitApprovalController extends Controller
{
    /**
     * Approve/Reject visit (for managers)
     */
    public function approveVisit(Request $request, $visitId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'required_if:action,reject|string|max:500',
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
            $visit = Visit::with('employee')->find($visitId);

            if (!$visit) {
                return response()->json([
                    'status' => false,
                    'message' => 'Visit not found'
                ], 404);
            }

            // Check if user can approve this visit
            if (!$approver->canApprove($visit->employee)) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to approve this visit'
                ], 403);
            }

            if (!$visit->isPending()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Visit is no longer pending approval'
                ], 400);
            }

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

            $action = $request->action;
            $status = $action === 'approve' ? 'approved' : 'rejected';

            $visit->update([
                'status' => $status,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            // Log activity
            ActivityLog::create([
                'employee_id' => $approver->id,
                'company_id' => $employee->company_id,
                'activity_type' => "visit_{$action}d",
                'description' => "Visit {$action}d for {$visit->employee->name}: {$visit->purpose}",
                'title' => "Visit {$action}",
                'activity_time' => now(),
                'metadata' => json_encode([
                    'visit_id' => $visit->id,
                    'employee_name' => $visit->employee->name,
                    'visit_type' => $visit->visit_type,
                    'rejection_reason' => $request->rejection_reason,
                ])
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => "Visit {$action}d successfully",
                'data' => [
                    'visit_id' => $visit->id,
                    'status' => $visit->status,
                    'approved_by' => $approver->name,
                    'approved_at' => $visit->approved_at->format('Y-m-d H:i:s'),
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
     * Get visits for approval (for managers)
     */
    public function getVisitsForApproval(Request $request): JsonResponse
    {
        try {
            $approver = $request->user()->employee;
            $status = $request->get('status', 'pending');
            $period = $request->get('period', 'month'); // today, week, month

            $query = Visit::forApprover($approver->id)->byStatus($status);

            // Add period filter if needed
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

            $visits = $query->with(['employee:id,name,position'])
                          ->orderBy('created_at', 'desc')
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
     * Get visit statistics for manager dashboard
     */
    public function getVisitStatistics(Request $request): JsonResponse
    {
        try {
            $approver = $request->user()->employee;
            $period = $request->get('period', 'month');

            $query = Visit::forApprover($approver->id);

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

            $statistics = [
                'total' => (clone $query)->count(),
                'pending' => (clone $query)->where('status', 'pending')->count(),
                'approved' => (clone $query)->where('status', 'approved')->count(),
                'rejected' => (clone $query)->where('status', 'rejected')->count(),
                'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
                'completed' => (clone $query)->where('status', 'completed')->count(),
            ];

            return response()->json([
                'status' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get visit details for approval
     */
    public function getVisitDetail(Request $request, $visitId): JsonResponse
    {
        try {
            $approver = $request->user()->employee;
            $visit = Visit::with(['employee:id,name,position,department', 'approver:id,name'])
                         ->where('id', $visitId)
                         ->first();

            if (!$visit) {
                return response()->json([
                    'status' => false,
                    'message' => 'Visit not found'
                ], 404);
            }

            // Check if user can view this visit
            if (!$approver->canApprove($visit->employee)) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to view this visit'
                ], 403);
            }

            return response()->json([
                'status' => true,
                'data' => $visit
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}