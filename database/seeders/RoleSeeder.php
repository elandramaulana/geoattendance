<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
  public function run()
    {
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Full system access'
            ],
            [
                'name' => 'Company Admin',
                'slug' => 'company-admin', 
                'description' => 'Company level administration'
            ],
            [
                'name' => 'HR Manager',
                'slug' => 'hr-manager',
                'description' => 'Human resource management'
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Team management and approvals'
            ],
            [
                'name' => 'Employee',
                'slug' => 'employee',
                'description' => 'Regular employee access'
            ]
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
