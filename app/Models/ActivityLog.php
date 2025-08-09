<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'type',
        'timestamp',
        'latitude',
        'longitude',
        'address',
        'photo',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // Scopes
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('timestamp', '>=', now()->subDays($days));
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}