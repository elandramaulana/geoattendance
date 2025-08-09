<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function getProfile(): JsonResponse
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            // Get the employee data with necessary relations
            $employee = Employee::with([
                'company',        // Load company relation
                'office',         // Load office relation
                'role',           // Load role relation
                'approver'        // Load approver relation
            ])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee data not found or inactive'
                ], 404);
            }

            // Structure the response data - gabung semua dalam satu data object
            $profileData = [
                // Data employee
                'id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'name' => $employee->name,
                'phone' => $employee->phone,
                'birth_date' => $employee->birth_date?->format('Y-m-d'),
                'gender' => $employee->gender,
                'address' => $employee->address,
                'avatar' => $employee->avatar,
                'position' => $employee->position,
                'department' => $employee->department,
                'hire_date' => $employee->hire_date?->format('Y-m-d'),
                'contract_end_date' => $employee->contract_end_date?->format('Y-m-d'),
                'employment_status' => $employee->employment_status,
                
                // Nama company
                'company_name' => $employee->company->name ?? null,
                
                // Nama office
                'office_name' => $employee->office->name ?? null,
                
                // Nama role
                'role_name' => $employee->role->name ?? null,
                
                // Nama approver
                'approver_name' => $employee->approver->name ?? null,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Profile data retrieved successfully',
                'data' => $profileData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching profile',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update employee profile data
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $employee = Employee::where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee data not found or inactive'
                ], 404);
            }

            // Validation rules for updatable fields
            $validator = Validator::make($request->all(), [
                'phone' => 'sometimes|string|max:20',
                'address' => 'sometimes|string|max:500',
                'birth_date' => 'sometimes|date',
                'gender' => 'sometimes|in:male,female',
                'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
                // Add other updatable fields validation
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update only allowed fields
            $updateData = $request->only([
                'phone', 'address', 'birth_date', 'gender', 'avatar'
                // Add other updatable fields
            ]);

            $employee->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'phone' => $employee->phone,
                    'address' => $employee->address,
                    'birth_date' => $employee->birth_date?->format('Y-m-d'),
                    'gender' => $employee->gender,
                    'avatar' => $employee->avatar,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating profile',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}