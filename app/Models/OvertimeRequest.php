<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'duration' => 'integer',
            'approved_at' => 'datetime',
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

    public function approvals()
    {
        return $this->morphMany(Approval::class, 'approvable');
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

    // Helper methods
    public function calculateDuration()
    {
        if ($this->start_time && $this->end_time) {
            $startTime = \Carbon\Carbon::parse($this->start_time);
            $endTime = \Carbon\Carbon::parse($this->end_time);
            
            $this->duration = $endTime->diffInMinutes($startTime);
            
            return $this->duration;
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
}
