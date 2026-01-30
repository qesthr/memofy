<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin
        User::updateOrCreate(
            ['email' => 'admin@buksu.edu.ph'],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'role' => 'admin',
                'department' => 'IT',
                'password' => Hash::make('Admin123!'),
                'is_active' => true,
            ]
        );

        // Secretary
        User::updateOrCreate(
            ['email' => 'secretary@buksu.edu.ph'],
            [
                'first_name' => 'Department',
                'last_name' => 'Secretary',
                'role' => 'secretary',
                'department' => 'Admin Office',
                'password' => Hash::make('Secretary123!'),
                'is_active' => true,
            ]
        );

        // Faculty
        User::updateOrCreate(
            ['email' => 'faculty@buksu.edu.ph'],
            [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'role' => 'faculty',
                'department' => 'College of Technologies',
                'password' => Hash::make('Faculty123!'),
                'is_active' => true,
            ]
        );
    }
}
