<?php
// database/seeders/OfficeSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Office;

class OfficeSeeder extends Seeder
{
   public function run()
    {
        $offices = [
            [
                'company_id' => 1,
                'name' => 'Jakarta Head Office',
                'code' => 'JKT001',
                'address' => 'Jl. Sudirman No. 123, Jakarta Pusat',
                'latitude' => -6.200000,
                'longitude' => 106.816666,
                'radius' => 100,
                'work_start_time' => '08:00:00',
                'work_end_time' => '17:00:00'
            ],
            [
                'company_id' => 1,
                'name' => 'Bandung Branch',
                'code' => 'BDG001', 
                'address' => 'Jl. Asia Afrika No. 45, Bandung',
                'latitude' => -6.917464,
                'longitude' => 107.619123,
                'radius' => 80,
                'work_start_time' => '08:00:00',
                'work_end_time' => '17:00:00'
            ]
        ];

        foreach ($offices as $office) {
            Office::create($office);
        }
    }
}