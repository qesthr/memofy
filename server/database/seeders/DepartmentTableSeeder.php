<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            ['name' => 'Food Technology', 'code' => 'FT'],
            ['name' => 'Automotive Technology', 'code' => 'AT'],
            ['name' => 'Electronics Technology', 'code' => 'ET'],
            ['name' => 'Information Technology/EMC', 'code' => 'IT'],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(['name' => $dept['name']], $dept);
        }
    }
}
