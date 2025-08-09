<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Employee;

class OfficeLocationController extends Controller
{
    /**
     * Get office location for authenticated employee
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getOfficeLocation(Request $request): JsonResponse
    {
        try {
            // Validasi input lat/long dari user
            $validator = Validator::make($request->all(), [
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Ambil user yang sedang login
            $user = Auth::user();
            
            // Ambil data employee dengan relasi office saja
            $employee = Employee::with('office')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee data not found or inactive'
                ], 404);
            }

            if (!$employee->office || !$employee->office->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Office data not found or inactive'
                ], 404);
            }

            // Ambil koordinat user
            $userLatitude = $request->latitude;
            $userLongitude = $request->longitude;
            
            // Hitung jarak menggunakan method yang sama seperti di Office model
            $distance = $this->calculateDistance(
                $userLatitude, 
                $userLongitude, 
                $employee->office->latitude, 
                $employee->office->longitude
            );

            // Cek apakah user berada dalam radius yang diizinkan menggunakan helper method
            $isWithinRadius = $employee->office->isWithinRadius($userLatitude, $userLongitude);

            return response()->json([
                'success' => true,
                'message' => 'Office location retrieved successfully',
                'data' => [
                    'office_latitude' => (float) $employee->office->latitude,
                    'office_longitude' => (float) $employee->office->longitude,
                    'allowed_radius' => $employee->office->radius,
                    'distance' => round($distance, 2),
                    'can_attendance' => $isWithinRadius
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving office location',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     * 
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float Distance in meters
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371000; // Earth radius in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLon/2) * sin($dLon/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
}