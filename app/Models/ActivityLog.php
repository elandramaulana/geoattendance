<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'company_id',
        'activity_type',
        'title',
        'description',
        'metadata',
        'latitude',
        'longitude',
        'location_address',
        'activity_time',
        'device_info',
        'ip_address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'activity_time' => 'datetime',
        ];
    }

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('activity_type', $type);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('activity_time', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('activity_time', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('activity_time', now()->month)
                    ->whereYear('activity_time', now()->year);
    }

    // Helper methods
    public static function logActivity(array $data)
    {
        return self::create(array_merge($data, [
            'activity_time' => now(),
            'ip_address' => request()->ip(),
            'device_info' => request()->userAgent(),
        ]));
    }

    public static function logClockIn($employee, $attendance, $lat, $lng, $address)
    {
        return self::logActivity([
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
            'activity_type' => 'clock_in',
            'title' => 'Clock In',
            'description' => "Clock in at {$address}",
            'metadata' => [
                'attendance_id' => $attendance->id,
                'office_id' => $attendance->office_id,
                'clock_in_time' => $attendance->clock_in,
            ],
            'latitude' => $lat,
            'longitude' => $lng,
            'location_address' => $address,
        ]);
    }

    public static function logClockOut($employee, $attendance, $lat, $lng, $address)
    {
        return self::logActivity([
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
            'activity_type' => 'clock_out',
            'title' => 'Clock Out',
            'description' => "Clock out at {$address}",
            'metadata' => [
                'attendance_id' => $attendance->id,
                'office_id' => $attendance->office_id,
                'clock_out_time' => $attendance->clock_out,
                'work_duration' => $attendance->work_duration,
                'overtime_duration' => $attendance->overtime_duration,
            ],
            'latitude' => $lat,
            'longitude' => $lng,
            'location_address' => $address,
        ]);
    }
}