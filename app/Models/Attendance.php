<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
        'work_duration',
        'overtime_duration',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in_lat' => 'decimal:8',
            'clock_in_lng' => 'decimal:8',
            'clock_out_lat' => 'decimal:8',
            'clock_out_lng' => 'decimal:8',
            'work_duration' => 'integer',
            'overtime_duration' => 'integer',
            // REMOVED: clock_in and clock_out casts as they should remain as strings
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

    // Helper methods - COMPLETELY FIXED VERSION
    public function calculateWorkDuration()
    {
        if ($this->clock_in && $this->clock_out) {
            try {
                // Get date string properly
                $dateString = $this->date instanceof Carbon ? 
                    $this->date->format('Y-m-d') : 
                    Carbon::parse($this->date)->format('Y-m-d');
                
                // FIX: Parse dengan benar menggunakan createFromFormat
                $clockInDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateString . ' ' . $this->clock_in);
                $clockOutDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateString . ' ' . $this->clock_out);
                
                // Hitung selisih dalam menit
                $duration = $clockOutDateTime->diffInMinutes($clockInDateTime);
                
                // Kurangi break duration jika ada
                $breakDuration = 0;
                if ($this->employee && $this->employee->workSchedule) {
                    $breakDuration = $this->employee->workSchedule->break_duration ?? 0;
                }
                
                $this->work_duration = max(0, $duration - $breakDuration);
                
                return $this->work_duration;
            } catch (\Exception $e) {
                \Log::error('Error in calculateWorkDuration: ' . $e->getMessage());
                $this->work_duration = 0;
                return 0;
            }
        }
        
        return 0;
    }

    public function calculateOvertimeDuration()
    {
        if ($this->work_duration) {
            try {
                $scheduledHours = 8; // default
                if ($this->employee && $this->employee->workSchedule) {
                    $scheduledHours = $this->employee->workSchedule->total_hours ?? 8;
                }
                
                $scheduledMinutes = $scheduledHours * 60;
                
                $this->overtime_duration = max(0, $this->work_duration - $scheduledMinutes);
                
                return $this->overtime_duration;
            } catch (\Exception $e) {
                \Log::error('Error in calculateOvertimeDuration: ' . $e->getMessage());
                $this->overtime_duration = 0;
                return 0;
            }
        }
        
        return 0;
    }

    public function isLate()
    {
        if (!$this->clock_in) return false;
        
        $scheduledStart = '08:00:00'; // default
        if ($this->employee && $this->employee->workSchedule) {
            $scheduledStart = $this->employee->workSchedule->start_time;
        }
        
        // FIX: Parse clock_in sebagai string
        $clockInTime = Carbon::parse($this->clock_in);
        $scheduledStartTime = Carbon::parse($scheduledStart);
        
        return $clockInTime->gt($scheduledStartTime);
    }

    // Helper methods untuk format durasi
    public function getWorkDurationInHours()
    {
        return $this->work_duration ? round($this->work_duration / 60, 2) : 0;
    }

    public function getWorkDurationFormatted()
    {
        if (!$this->work_duration) return '0 jam 0 menit';
        
        $hours = floor($this->work_duration / 60);
        $minutes = $this->work_duration % 60;
        
        return "{$hours} jam {$minutes} menit";
    }

    public function getOvertimeDurationInHours()
    {
        return $this->overtime_duration ? round($this->overtime_duration / 60, 2) : 0;
    }

    public function getOvertimeDurationFormatted()
    {
        if (!$this->overtime_duration) return '0 jam 0 menit';
        
        $hours = floor($this->overtime_duration / 60);
        $minutes = $this->overtime_duration % 60;
        
        return "{$hours} jam {$minutes} menit";
    }

    /**
     * Get formatted clock in time
     */
    public function getClockInFormatted()
    {
        return $this->clock_in ? Carbon::parse($this->clock_in)->format('H:i') : null;
    }

    /**
     * Get formatted clock out time
     */
    public function getClockOutFormatted()
    {
        return $this->clock_out ? Carbon::parse($this->clock_out)->format('H:i') : null;
    }

    /**
     * Get full clock in datetime
     */
    public function getClockInDateTime()
    {
        if (!$this->clock_in || !$this->date) return null;
        
        return Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->clock_in);
    }

    /**
     * Get full clock out datetime
     */
    public function getClockOutDateTime()
    {
        if (!$this->clock_out || !$this->date) return null;
        
        return Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->clock_out);
    }
}