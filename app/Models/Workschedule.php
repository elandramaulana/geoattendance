<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'work_days',
        'total_hours',
        'break_duration',
        'is_flexible',
        'flexible_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'work_days' => 'array', // This should handle JSON conversion
            'total_hours' => 'integer',
            'break_duration' => 'integer',
            'flexible_minutes' => 'integer',
            'is_flexible' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    // Helper methods
    public function isWorkDay($dayOfWeek)
    {
        // Ensure work_days is an array
        $workDays = $this->work_days;
        
        // Handle if casting failed or data is corrupted
        if (is_string($workDays)) {
            $workDays = json_decode($workDays, true);
        }
        
        // Final safety check
        if (!is_array($workDays)) {
            return false;
        }
        
        return in_array($dayOfWeek, $workDays);
    }

    public function getFlexibleStartTime()
    {
        if (!$this->is_flexible) {
            return $this->start_time;
        }

        $startTime = \Carbon\Carbon::parse($this->start_time);
        return $startTime->subMinutes($this->flexible_minutes);
    }

    public function getFlexibleEndTime()
    {
        if (!$this->is_flexible) {
            return $this->end_time;
        }

        $endTime = \Carbon\Carbon::parse($this->end_time);
        return $endTime->addMinutes($this->flexible_minutes);
    }

    // Accessor to ensure work_days is always array
    public function getWorkDaysAttribute($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        
        return is_array($value) ? $value : [];
    }

    // Mutator to ensure work_days is stored as JSON
    public function setWorkDaysAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['work_days'] = json_encode($value);
        } elseif (is_string($value)) {
            // If it's already JSON, validate and store
            $decoded = json_decode($value, true);
            $this->attributes['work_days'] = is_array($decoded) ? $value : json_encode([]);
        } else {
            $this->attributes['work_days'] = json_encode([]);
        }
    }
}