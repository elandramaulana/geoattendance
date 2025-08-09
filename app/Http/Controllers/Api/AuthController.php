<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Employee;
use App\Models\Company;
use App\Models\Office;
use App\Models\Role;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Register new user
     */
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'phone' => 'nullable|string|max:20',
                'company_id' => 'required|exists:companies,id',
                'office_id' => 'required|exists:offices,id',
                'employee_id' => 'required|string|unique:employees,employee_id',
                'position' => 'nullable|string|max:255',
                'department' => 'nullable|string|max:255',
                'birth_date' => 'nullable|date',
                'gender' => 'nullable|in:male,female',
                'address' => 'nullable|string',
                'hire_date' => 'nullable|date',
                'work_schedule_id' => 'nullable|exists:work_schedules,id',
                'approver_id' => 'nullable|exists:employees,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if office belongs to company
            $office = Office::where('id', $request->office_id)
                          ->where('company_id', $request->company_id)
                          ->first();
            
            if (!$office) {
                return response()->json([
                    'success' => false,
                    'message' => 'Office does not belong to the specified company'
                ], 422);
            }

            DB::beginTransaction();

            // Create User
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Get default role (Employee)
            $defaultRole = Role::where('slug', 'employee')->first();
            if (!$defaultRole) {
                $defaultRole = Role::first(); // fallback to first role
            }

            // Get default work schedule
            $workScheduleId = $request->work_schedule_id ?? WorkSchedule::where('is_active', true)->first()?->id;

            // Create Employee
            $employee = Employee::create([
                'user_id' => $user->id,
                'company_id' => $request->company_id,
                'office_id' => $request->office_id,
                'role_id' => $defaultRole->id,
                'work_schedule_id' => $workScheduleId,
                'employee_id' => $request->employee_id,
                'name' => $request->name,
                'phone' => $request->phone,
                'position' => $request->position,
                'department' => $request->department,
                'birth_date' => $request->birth_date,
                'gender' => $request->gender,
                'address' => $request->address,
                'hire_date' => $request->hire_date ?? Carbon::now(),
                'employment_status' => 'permanent',
                'approver_id' => $request->approver_id,
                'is_active' => true
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'user_id' => $user->id,
                    'employee_id' => $employee->employee_id,
                    'name' => $employee->name,
                    'email' => $user->email
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login user
     */
     public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find user
            $user = User::where('email', $request->email)->first();

            // Check credentials
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check if employee exists and is active
            $employee = $user->employee;
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee profile not found'
                ], 404);
            }

            if (!$employee->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is inactive. Please contact administrator.'
                ], 403);
            }
            
             $user->tokens()
            ->where('last_used_at', '<', now()->subDays(30))
            ->delete();

            // Create new token
            $token = $user->createToken('auth-token')->plainTextToken;

            // Load employee with role
            $employee->load('role');

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'token' => $token,
                    'user_id' => $user->id,
                    'role' => $employee->role->name
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        try {
            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request)
    {
        try {
            // Revoke all tokens
            $request->user()->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out from all devices successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}