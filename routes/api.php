<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OfficeLocationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AttendanceHistoryController;

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
    // Office location endpoint
    Route::post('office/location', [OfficeLocationController::class, 'getOfficeLocation']);
    Route::get('user/profile', [ProfileController::class, 'getProfile']);

      // Attendance history routes
    Route::prefix('attendance')->group(function () {
          // Get attendance summary/statistics
        Route::get('/history/summary', [AttendanceHistoryController::class, 'summary']);
        // Get specific attendance detail
        Route::get('/history/{id}', [AttendanceHistoryController::class, 'show']);
          // Get attendance history list with filters and pagination
        Route::get('/history', [AttendanceHistoryController::class, 'index']);
      
    });
});

