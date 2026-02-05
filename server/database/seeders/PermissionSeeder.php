<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            // Reports & Analytics
            [
                'name' => 'reports.view',
                'description' => 'View reports dashboard'
            ],
            [
                'name' => 'reports.view_analytics',
                'description' => 'View detailed analytics'
            ],
            [
                'name' => 'reports.export',
                'description' => 'Export reports data'
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}
