<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\WorkSchedule;

class WorkScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $schedules = [
            [
                'name' => 'Regular Schedule (Mon-Fri)',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'work_days' => [1, 2, 3, 4, 5], // Monday to Friday
                'total_hours' => 8,
                'break_duration' => 60,
                'is_flexible' => false,
                'flexible_minutes' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Flexible Schedule (Mon-Fri)',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'work_days' => [1, 2, 3, 4, 5], // Monday to Friday
                'total_hours' => 8,
                'break_duration' => 60,
                'is_flexible' => true,
                'flexible_minutes' => 15, // 15 minutes tolerance
                'is_active' => true,
            ],
            [
                'name' => 'Shift Schedule (Mon-Sat)',
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'work_days' => [1, 2, 3, 4, 5, 6], // Monday to Saturday
                'total_hours' => 8,
                'break_duration' => 60,
                'is_flexible' => true,
                'flexible_minutes' => 30,
                'is_active' => true,
            ],
            [
                'name' => 'Part-time Schedule',
                'start_time' => '13:00:00',
                'end_time' => '17:00:00',
                'work_days' => [1, 2, 3, 4, 5], // Monday to Friday
                'total_hours' => 4,
                'break_duration' => 0,
                'is_flexible' => false,
                'flexible_minutes' => 0,
                'is_active' => true,
            ],
        ];

        foreach ($schedules as $schedule) {
            WorkSchedule::create($schedule);
        }
    }
}