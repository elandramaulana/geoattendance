<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Visit extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'attendance_id',
        'visit_type',
        'purpose',
        'location_name',
        'client_name',
        'planned_start_time',
        'planned_end_time',
        'actual_start_time',
        'actual_end_time',
        'start_lat',
        'start_lng',
        'end_lat',
        'end_lng',
        'start_address',
        'end_address',
        'status',
        'approved_by',
        'approved_at',
        'notes',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_time' => 'datetime',
            'planned_end_time' => 'datetime',
            'actual_start_time' => 'datetime',
            'actual_end_time' => 'datetime',
            'approved_at' => 'datetime',
            'start_lat' => 'decimal:8',
            'start_lng' => 'decimal:8',
            'end_lat' => 'decimal:8',
            'end_lng' => 'decimal:8',
        ];
    }

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    // Status helpers
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function canStartVisit(): bool
    {
        return $this->isApproved() && !$this->actual_start_time;
    }

    public function canEndVisit(): bool
    {
        return $this->isInProgress() && $this->actual_start_time && !$this->actual_end_time;
    }

    // Format helpers
    public function getFormattedPlannedStartTime(): string
    {
        return $this->planned_start_time->format('Y-m-d H:i');
    }

    public function getFormattedPlannedEndTime(): string
    {
        return $this->planned_end_time->format('Y-m-d H:i');
    }

    public function getFormattedActualStartTime(): ?string
    {
        return $this->actual_start_time ? $this->actual_start_time->format('Y-m-d H:i') : null;
    }

    public function getFormattedActualEndTime(): ?string
    {
        return $this->actual_end_time ? $this->actual_end_time->format('Y-m-d H:i') : null;
    }

    public function getDuration(): ?int
    {
        if ($this->actual_start_time && $this->actual_end_time) {
            return $this->actual_end_time->diffInMinutes($this->actual_start_time);
        }
        return null;
    }

    public function getFormattedDuration(): string
    {
        $duration = $this->getDuration();
        if (!$duration) return '0 jam 0 menit';
        
        $hours = floor($duration / 60);
        $minutes = $duration % 60;
        
        return "{$hours} jam {$minutes} menit";
    }

    // Scopes
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForApprover($query, $approverId)
    {
        return $query->whereHas('employee', function($q) use ($approverId) {
            $q->where('approver_id', $approverId);
        });
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('planned_start_time', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('planned_start_time', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('planned_start_time', [
            now()->startOfMonth(),
            now()->endOfMonth()
        ]);
    }
}