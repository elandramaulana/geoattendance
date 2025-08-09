<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\WorkSchedule;

class WorkScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   public function run()
    {
        $schedules = [
            [
                'name' => 'Regular Schedule',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'work_days' => json_encode([1,2,3,4,5]), // Mon-Fri
                'total_hours' => 8,
                'break_duration' => 60,
                'is_flexible' => false
            ],
            [
                'name' => 'Flexible Schedule',
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'work_days' => json_encode([1,2,3,4,5]),
                'total_hours' => 8,
                'break_duration' => 60,
                'is_flexible' => true,
                'flexible_minutes' => 60
            ],
            [
                'name' => 'Shift Schedule',
                'start_time' => '13:00:00',
                'end_time' => '22:00:00',
                'work_days' => json_encode([1,2,3,4,5,6]),
                'total_hours' => 8,
                'break_duration' => 60,
                'is_flexible' => false
            ]
        ];

        foreach ($schedules as $schedule) {
            WorkSchedule::create($schedule);
        }
    }
}
