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
        // Admin Users (Admin1 to Admin3)
        for ($i = 1; $i <= 3; $i++) {
            User::updateOrCreate(
                ['email' => "Admin{$i}@buksu.edu.ph"],
                [
                    'first_name' => 'System',
                    'last_name' => "Admin {$i}",
                    'role' => 'admin',
                    'department' => 'IT',
                    'password' => Hash::make('Admin123!'),
                    'is_active' => true,
                ]
            );
        }

        // Secretary Users (Secretary1 to Secretary3)
        for ($i = 1; $i <= 3; $i++) {
            User::updateOrCreate(
                ['email' => "Secretary{$i}@buksu.edu.ph"],
                [
                    'first_name' => 'Department',
                    'last_name' => "Secretary {$i}",
                    'role' => 'secretary',
                    'department' => 'Admin Office',
                    'password' => Hash::make('Secretary123!'),
                    'is_active' => true,
                ]
            );
        }

        // Faculty Users (Faculty1 to Faculty3)
        for ($i = 1; $i <= 3; $i++) {
            User::updateOrCreate(
                ['email' => "Faculty{$i}@buksu.edu.ph"],
                [
                    'first_name' => 'Juan',
                    'last_name' => "Dela Cruz {$i}",
                    'role' => 'faculty',
                    'department' => 'College of Technologies',
                    'password' => Hash::make('Faculty123!'),
                    'is_active' => true,
                ]
            );
        }
    }
}
