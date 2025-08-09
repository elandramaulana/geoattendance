<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'max_days_per_year',
        'is_paid',
        'requires_approval',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'max_days_per_year' => 'integer',
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
