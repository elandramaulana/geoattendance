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
            'work_days' => 'array',
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
        return in_array($dayOfWeek, $this->work_days);
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
}