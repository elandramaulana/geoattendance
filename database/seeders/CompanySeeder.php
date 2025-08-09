<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
        {
            Company::create([
                'name' => 'Tech Solutions Inc',
                'code' => 'TSI001',
                'email' => 'admin@techsolutions.com',
                'phone' => '+62-21-12345678',
                'address' => 'Jl. Sudirman No. 123, Jakarta Pusat',
                'settings' => json_encode([
                    'work_hours' => 8,
                    'overtime_rate' => 1.5,
                    'late_tolerance' => 15
                ])
            ]);
        }
}
