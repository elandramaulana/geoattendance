<?php
// database/seeders/HolidaySeeder.php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use App\Models\Holiday;


class HolidaySeeder extends Seeder
{
    public function run()
    {
        $holidays = [
            [
                'company_id' => 1,
                'name' => 'New Year',
                'date' => '2025-01-01',
                'description' => 'New Year Holiday'
            ],
            [
                'company_id' => 1,
                'name' => 'Independence Day',
                'date' => '2025-08-17',
                'description' => 'Indonesian Independence Day'
            ],
            [
                'company_id' => 1,
                'name' => 'Christmas',
                'date' => '2025-12-25', 
                'description' => 'Christmas Holiday'
            ]
        ];

        foreach ($holidays as $holiday) {
            Holiday::create($holiday);
        }
    }
}