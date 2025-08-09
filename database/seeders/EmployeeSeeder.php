<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Employee;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
   public function run()
    {
        $employees = [
            [
                'user_id' => 1,
                'company_id' => 1,
                'office_id' => 1,
                'role_id' => 1,
                'work_schedule_id' => 1,
                'employee_id' => 'EMP001',
                'name' => 'Super Admin',
                'phone' => '+62-812-3456-7890',
                'position' => 'System Administrator',
                'department' => 'IT',
                'hire_date' => '2024-01-01',
                'employment_status' => 'permanent',
                'salary' => 15000000.00
            ],
            [
                'user_id' => 2,
                'company_id' => 1,
                'office_id' => 1,
                'role_id' => 4,
                'work_schedule_id' => 1,
                'employee_id' => 'EMP002',
                'name' => 'John Manager',
                'phone' => '+62-812-3456-7891',
                'position' => 'Team Lead',
                'department' => 'Development',
                'hire_date' => '2024-01-15',
                'employment_status' => 'permanent',
                'salary' => 12000000.00
            ],
            [
                'user_id' => 3,
                'company_id' => 1,
                'office_id' => 1,
                'role_id' => 5,
                'work_schedule_id' => 2,
                'employee_id' => 'EMP003',
                'name' => 'Jane Employee',
                'phone' => '+62-812-3456-7892',
                'position' => 'Frontend Developer',
                'department' => 'Development',
                'hire_date' => '2024-02-01',
                'employment_status' => 'permanent',
                'salary' => 8000000.00,
                'approver_id' => 2 // John Manager as approver
            ]
        ];

        foreach ($employees as $employee) {
            Employee::create($employee);
        }
    }
}
