<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LeaveType;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $leaveTypes = [
            [
                'company_id' => 1,
                'name' => 'Annual Leave',
                'code' => 'AL',
                'max_days_per_year' => 12,
                'is_paid' => true,
                'description' => 'Yearly vacation leave'
            ],
            [
                'company_id' => 1,
                'name' => 'Sick Leave', 
                'code' => 'SL',
                'max_days_per_year' => 30,
                'is_paid' => true,
                'description' => 'Medical leave'
            ],
            [
                'company_id' => 1,
                'name' => 'Emergency Leave',
                'code' => 'EL',
                'max_days_per_year' => 5,
                'is_paid' => true,
                'description' => 'Urgent personal matters'
            ]
        ];

        foreach ($leaveTypes as $leaveType) {
            LeaveType::create($leaveType);
        }
    }
}
