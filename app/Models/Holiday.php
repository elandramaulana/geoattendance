<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'date',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Scopes
    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', now())->orderBy('date');
    }

    public function scopeThisYear($query)
    {
        return $query->whereYear('date', now()->year);
    }

    // Helper methods
    public static function isHoliday($date, $companyId)
    {
        return self::where('company_id', $companyId)
                  ->where('date', $date)
                  ->where('is_active', true)
                  ->exists();
    }
}
