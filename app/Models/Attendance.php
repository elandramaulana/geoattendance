<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'office_id',
        'date',
        'clock_in',
        'clock_out',
        'clock_in_lat',
        'clock_in_lng',
        'clock_out_lat',
        'clock_out_lng',
        'clock_in_address',
        'clock_out_address',
        'clock_in_photo',
        'clock_out_photo',
        'work_duration',
        'overtime_duration',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in' => 'datetime:H:i',
            'clock_out' => 'datetime:H:i',
            'clock_in_lat' => 'decimal:8',
            'clock_in_lng' => 'decimal:8',
            'clock_out_lat' => 'decimal:8',
            'clock_out_lng' => 'decimal:8',
            'work_duration' => 'integer',
            'overtime_duration' => 'integer',
        ];
    }

    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    // Helper methods
    public function calculateWorkDuration()
    {
        if ($this->clock_in && $this->clock_out) {
            $clockIn = \Carbon\Carbon::parse($this->clock_in);
            $clockOut = \Carbon\Carbon::parse($this->clock_out);
            
            $duration = $clockOut->diffInMinutes($clockIn);
            
            // Subtract break duration
            $breakDuration = $this->employee->workSchedule->break_duration ?? 60;
            $this->work_duration = max(0, $duration - $breakDuration);
            
            return $this->work_duration;
        }
        
        return 0;
    }

    public function calculateOvertimeDuration()
    {
        if ($this->work_duration) {
            $scheduledHours = $this->employee->workSchedule->total_hours ?? 8;
            $scheduledMinutes = $scheduledHours * 60;
            
            $this->overtime_duration = max(0, $this->work_duration - $scheduledMinutes);
            
            return $this->overtime_duration;
        }
        
        return 0;
    }

    public function isLate()
    {
        if (!$this->clock_in) return false;
        
        $scheduledStart = $this->employee->workSchedule->start_time;
        $clockIn = \Carbon\Carbon::parse($this->clock_in);
        $scheduledStartTime = \Carbon\Carbon::parse($scheduledStart);
        
        return $clockIn->gt($scheduledStartTime);
    }
}