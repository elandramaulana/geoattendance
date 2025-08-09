<?php
// database/seeders/UserSeeder.php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
   public function run()
    {
        $users = [
            [
                'email' => 'admin@techsolutions.com',
                'password' => Hash::make('password123'),
            ],
            [
                'email' => 'john.manager@techsolutions.com', 
                'password' => Hash::make('password123'),
            ],
            [
                'email' => 'jane.employee@techsolutions.com',
                'password' => Hash::make('password123'),
            ]
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}