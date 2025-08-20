<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OfficeLocationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AttendanceHistoryController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\OvertimeRequestController;
use App\Http\Controllers\Api\OvertimeApprovalController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\Api\VisitApprovalController;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});

// Protected routes
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('logout-all', [AuthController::class, 'logoutAll']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('office/location', [OfficeLocationController::class, 'getOfficeLocation']);
    
    Route::get('user/profile', [ProfileController::class, 'getProfile']);

    Route::prefix('attendance')->group(function () {
        Route::get('/history/summary', [AttendanceHistoryController::class, 'summary']);
        Route::get('/history/{id}', [AttendanceHistoryController::class, 'show']);
        Route::get('/history', [AttendanceHistoryController::class, 'index']);
    });

    Route::prefix('attendance')->group(function () {
        Route::post('/clock', [AttendanceController::class, 'clockInOut']);
        Route::get('/status', [AttendanceController::class, 'getAttendanceStatus']);
        Route::get('/activities', [AttendanceController::class, 'getActivityLogs']);
    });

     Route::prefix('overtime')->group(function () {
        Route::get('/', [OvertimeRequestController::class, 'index']); // Get employee's overtime requests
        Route::post('/', [OvertimeRequestController::class, 'store']); // Submit overtime request
        Route::get('/{id}', [OvertimeRequestController::class, 'show']); // Get specific request
        Route::put('/{id}/cancel', [OvertimeRequestController::class, 'cancel']); // Cancel pending request
    });

    // Manager/Admin Overtime Approval Routes  
    Route::prefix('overtime-approval')->group(function () {
        Route::get('/pending', [OvertimeApprovalController::class, 'getPendingRequests']); // Get pending requests
        Route::put('/{id}/approve', [OvertimeApprovalController::class, 'approve']); // Approve request
        Route::put('/{id}/reject', [OvertimeApprovalController::class, 'reject']); // Reject request
    });

    Route::prefix('employee/visits')->group(function () {
        Route::post('request', [VisitController::class, 'requestVisit']);
        Route::post('{visitId}/start', [VisitController::class, 'startVisit']);
        Route::post('{visitId}/end', [VisitController::class, 'endVisit']);
        Route::get('my-visits', [VisitController::class, 'getMyVisits']);
    });

    Route::prefix('approval/visits')->group(function () {
        Route::post('{visitId}/approve', [VisitApprovalController::class, 'approveVisit']);
        Route::get('for-approval', [VisitApprovalController::class, 'getVisitsForApproval']);
        Route::get('statistics', [VisitApprovalController::class, 'getVisitStatistics']);
        Route::get('{visitId}/detail', [VisitApprovalController::class, 'getVisitDetail']);
    });

});

