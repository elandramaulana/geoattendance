<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'office_id',
        'role_id',
        'work_schedule_id',
        'employee_id',
        'name',
        'phone',
        'birth_date',
        'gender',
        'address',
        'avatar',
        'position',
        'department',
        'hire_date',
        'contract_end_date',
        'employment_status',
        'salary',
        'approver_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hire_date' => 'date',
            'contract_end_date' => 'date',
            'salary' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'approver_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }

    public function approvedLeaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'approved_by');
    }

    public function approvedOvertimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class, 'approved_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function givenApprovals(): HasMany
    {
        return $this->hasMany(Approval::class, 'approver_id');
    }

    // Helper methods
    public function getTodayAttendance()
    {
        return $this->attendances()->whereDate('date', today())->first();
    }

    public function hasRole($roleSlug)
    {
        return $this->role->slug === $roleSlug;
    }

    public function canApprove(Employee $employee)
    {
        return $employee->approver_id === $this->id;
    }
}