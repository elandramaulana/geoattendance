<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class AttendanceHistoryController extends Controller
{
    /**
     * Get attendance history for authenticated employee
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get authenticated user's employee record
            $employee = Employee::where('user_id', auth()->id())->first();
            
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee record not found'
                ], 404);
            }

            // Validate request parameters
            $request->validate([
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'status' => 'nullable|in:present,late,absent,holiday,leave',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            // Build query
            $query = Attendance::where('employee_id', $employee->id)
                ->with(['office:id,name'])
                ->orderBy('date', 'desc');

            // Apply filters
            $this->applyFilters($query, $request);

            // Get pagination settings
            $perPage = $request->get('per_page', 15);
            
            // Get paginated results
            $attendances = $query->paginate($perPage);

            // Format the response
            $formattedData = $attendances->getCollection()->map(function ($attendance) {
                return $this->formatAttendanceData($attendance);
            });

            return response()->json([
                'success' => true,
                'message' => 'Attendance history retrieved successfully',
                'data' => [
                    'attendances' => $formattedData,
                    'pagination' => [
                        'current_page' => $attendances->currentPage(),
                        'last_page' => $attendances->lastPage(),
                        'per_page' => $attendances->perPage(),
                        'total' => $attendances->total(),
                        'from' => $attendances->firstItem(),
                        'to' => $attendances->lastItem()
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance history',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get specific attendance detail
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $employee = Employee::where('user_id', auth()->id())->first();
            
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee record not found'
                ], 404);
            }

            $attendance = Attendance::where('id', $id)
                ->where('employee_id', $employee->id)
                ->with(['office:id,name'])
                ->first();

            if (!$attendance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance record not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Attendance detail retrieved successfully',
                'data' => $this->formatAttendanceData($attendance, true)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance detail',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get attendance summary/statistics
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $employee = Employee::where('user_id', auth()->id())->first();
            
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee record not found'
                ], 404);
            }

            // Validate request
            $request->validate([
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            // Build summary query
            $query = Attendance::where('employee_id', $employee->id);
            
            // Apply same filters as history
            $this->applySummaryFilters($query, $request);
            
            // Get attendance summary
            $summary = $query->selectRaw('
                COUNT(*) as total_days,
                SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status = "leave" THEN 1 ELSE 0 END) as leave_days,
                SUM(CASE WHEN status = "holiday" THEN 1 ELSE 0 END) as holiday_days,
                SUM(COALESCE(work_duration, 0)) as total_work_minutes,
                SUM(COALESCE(overtime_duration, 0)) as total_overtime_minutes
            ')->first();

            // Calculate additional metrics
            $totalWorkHours = round(($summary->total_work_minutes ?? 0) / 60, 2);
            $totalOvertimeHours = round(($summary->total_overtime_minutes ?? 0) / 60, 2);
            $attendanceRate = $summary->total_days > 0 ? 
                round((($summary->present_days + $summary->late_days) / $summary->total_days) * 100, 2) : 0;

            // Determine period description
            $periodDescription = $this->getPeriodDescription($request);

            return response()->json([
                'success' => true,
                'message' => 'Attendance summary retrieved successfully',
                'data' => [
                    'period' => $periodDescription,
                    'summary' => [
                        'total_days' => $summary->total_days ?? 0,
                        'present_days' => $summary->present_days ?? 0,
                        'late_days' => $summary->late_days ?? 0,
                        'absent_days' => $summary->absent_days ?? 0,
                        'leave_days' => $summary->leave_days ?? 0,
                        'holiday_days' => $summary->holiday_days ?? 0,
                        'attendance_rate' => $attendanceRate,
                        'total_work_hours' => $totalWorkHours,
                        'total_overtime_hours' => $totalOvertimeHours
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance summary',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    private function getPeriodDescription(Request $request): string
    {
        if ($request->filled('month') && $request->filled('year')) {
            $monthName = Carbon::create($request->year, $request->month, 1)->format('F');
            return "{$monthName} {$request->year}";
        }
        
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->start_date)->format('d M Y');
            $endDate = Carbon::parse($request->end_date)->format('d M Y');
            return "{$startDate} - {$endDate}";
        }
        
        if ($request->filled('start_date')) {
            $startDate = Carbon::parse($request->start_date)->format('d M Y');
            return "Start {$startDate}";
        }
        
        if ($request->filled('end_date')) {
            $endDate = Carbon::parse($request->end_date)->format('d M Y');
            return "End {$endDate}";
        }
        
        if ($request->filled('year')) {
            return "Year {$request->year}";
        }
        
        if ($request->filled('month')) {
            $monthName = Carbon::create(now()->year, $request->month, 1)->format('F');
            return "{$monthName} " . now()->year;
        }
        
        return "All Periods";
    }

    /**
     * Apply filters to the query
     */
    private function applyFilters($query, Request $request)
    {
        // Filter by month and year (if both provided)
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('date', $request->month)
                  ->whereYear('date', $request->year);
        }
        // Filter by date range (if both provided)
        elseif ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }
        // Filter by start_date only (if only start_date provided)
        elseif ($request->filled('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }
        // Filter by end_date only (if only end_date provided)
        elseif ($request->filled('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }
        // Filter by year only (if only year provided)
        elseif ($request->filled('year')) {
            $query->whereYear('date', $request->year);
        }
        // Filter by month only (if only month provided, use current year)
        elseif ($request->filled('month')) {
            $query->whereMonth('date', $request->month)
                  ->whereYear('date', now()->year);
        }
        // If no date filter provided, show ALL history (no date restriction)

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
    }

    /**
     * Format attendance data for response - FIXED VERSION
     */
    private function formatAttendanceData($attendance, $includeDetails = false)
    {
        $formatted = [
            'id' => $attendance->id,
            'date' => $attendance->date->format('Y-m-d'),
            'day_name' => $attendance->date->format('l'),
            // FIX: Handle time fields as strings, not Carbon instances
            'clock_in' => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : null,
            'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : null,
            'status' => $attendance->status,
            'status_label' => $this->getStatusLabel($attendance->status),
            'work_duration_minutes' => $attendance->work_duration ?? 0,
            'work_duration_hours' => $attendance->work_duration ? round($attendance->work_duration / 60, 2) : 0,
            'overtime_duration_minutes' => $attendance->overtime_duration ?? 0,
            'overtime_duration_hours' => $attendance->overtime_duration ? round($attendance->overtime_duration / 60, 2) : 0,
            'office' => $attendance->office ? [
                'id' => $attendance->office->id,
                'name' => $attendance->office->name
            ] : null,
            'notes' => $attendance->notes
        ];

        // Include detailed information if requested
        if ($includeDetails) {
            $formatted = array_merge($formatted, [
                'clock_in_location' => [
                    'latitude' => $attendance->clock_in_lat,
                    'longitude' => $attendance->clock_in_lng,
                    'address' => $attendance->clock_in_address
                ],
                'clock_out_location' => [
                    'latitude' => $attendance->clock_out_lat,
                    'longitude' => $attendance->clock_out_lng,
                    'address' => $attendance->clock_out_address
                ],
                'created_at' => $attendance->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $attendance->updated_at->format('Y-m-d H:i:s')
            ]);
        }

        return $formatted;
    }

    /**
     * Apply filters to the summary query
     */
    private function applySummaryFilters($query, Request $request)
    {
        // Same filter logic as history, but for summary
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('date', $request->month)
                  ->whereYear('date', $request->year);
        }
        elseif ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }
        elseif ($request->filled('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }
        elseif ($request->filled('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }
        elseif ($request->filled('year')) {
            $query->whereYear('date', $request->year);
        }
        elseif ($request->filled('month')) {
            $query->whereMonth('date', $request->month)
                  ->whereYear('date', now()->year);
        }
        // If no parameters, include ALL attendance records (no date filter)
    }

    /**
     * Get status label in Indonesian
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'present' => 'Hadir',
            'late' => 'Terlambat',
            'absent' => 'Tidak Hadir',
            'holiday' => 'Libur',
            'leave' => 'Cuti'
        ];

        return $labels[$status] ?? ucfirst($status);
    }
}