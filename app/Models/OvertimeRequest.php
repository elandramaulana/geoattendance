<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OvertimeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'start_time',
        'end_time',
        'duration',
        'reason',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'duration' => 'integer',
            'approved_at' => 'datetime',
            // start_time dan end_time tetap sebagai string (TIME format)
        ];
    }

    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('date', Carbon::today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('date', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('date', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth()
        ]);
    }

    // Helper methods
    public function calculateDuration()
    {
        if ($this->start_time && $this->end_time) {
            try {
                // Parse time dengan date hari ini untuk calculation
                $dateString = $this->date instanceof Carbon ? 
                    $this->date->format('Y-m-d') : 
                    Carbon::parse($this->date)->format('Y-m-d');
                
                $startDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateString . ' ' . $this->start_time);
                $endDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateString . ' ' . $this->end_time);
                
                // Handle cross-day overtime (end_time < start_time)
                if ($endDateTime->lt($startDateTime)) {
                    $endDateTime->addDay();
                }
                
                $duration = $endDateTime->diffInMinutes($startDateTime);
                $this->duration = max(0, $duration);
                
                return $this->duration;
            } catch (\Exception $e) {
                \Log::error('Error calculating overtime duration: ' . $e->getMessage());
                return 0;
            }
        }
        
        return 0;
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    /**
     * Get formatted duration (HH:MM)
     */
    public function getFormattedDuration()
    {
        if (!$this->duration) return '00:00';
        
        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;
        
        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Get start time formatted
     */
    public function getFormattedStartTime()
    {
        return $this->start_time ? Carbon::parse($this->start_time)->format('H:i') : null;
    }

    /**
     * Get end time formatted  
     */
    public function getFormattedEndTime()
    {
        return $this->end_time ? Carbon::parse($this->end_time)->format('H:i') : null;
    }

    /**
     * Check if overtime request overlaps with existing approved overtime
     */
    public function hasOverlapWith($startTime, $endTime, $date = null, $excludeId = null)
    {
        $query = self::where('employee_id', $this->employee_id)
            ->where('status', 'approved');
            
        if ($date) {
            $query->whereDate('date', $date);
        } else {
            $query->whereDate('date', $this->date);
        }
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->where(function($q) use ($startTime, $endTime) {
            $q->where(function($subQ) use ($startTime, $endTime) {
                // Case 1: New start time is between existing start and end
                $subQ->where('start_time', '<=', $startTime)
                     ->where('end_time', '>', $startTime);
            })->orWhere(function($subQ) use ($startTime, $endTime) {
                // Case 2: New end time is between existing start and end  
                $subQ->where('start_time', '<', $endTime)
                     ->where('end_time', '>=', $endTime);
            })->orWhere(function($subQ) use ($startTime, $endTime) {
                // Case 3: New request completely encompasses existing
                $subQ->where('start_time', '>=', $startTime)
                     ->where('end_time', '<=', $endTime);
            });
        })->exists();
    }

    /**
     * Boot function - FIXED to not interfere with explicit duration setting
     */
    protected static function boot()
    {
        parent::boot();
        
        // Only auto-calculate duration if not already set
        static::saving(function ($overtimeRequest) {
            if ($overtimeRequest->start_time && $overtimeRequest->end_time && 
                ($overtimeRequest->duration === null || $overtimeRequest->duration === 0)) {
                $overtimeRequest->calculateDuration();
            }
        });
        
        // Only auto-calculate on creating if duration not provided
        static::creating(function ($overtimeRequest) {
            if ($overtimeRequest->start_time && $overtimeRequest->end_time && 
                ($overtimeRequest->duration === null || $overtimeRequest->duration === 0)) {
                $overtimeRequest->calculateDuration();
            }
        });
    }
}