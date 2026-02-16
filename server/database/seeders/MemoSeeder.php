<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class MemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Usage: 
     *   php artisan db:seed --class=MemoSeeder
     *
     * For custom count, use the dedicated command:
     *   php artisan memos:seed --count=150
     *   php artisan memos:seed --count=200 --truncate
     */
    public function run(): void
    {
        $this->command->info('Running MemoSeeder (default: 100 memos)...');
        $this->command->info('For custom count, use: php artisan memos:seed --count=150');
        $this->command->info('To clear existing memos: php artisan memos:seed --count=150 --truncate');
        
        Artisan::call('memos:seed', ['--count' => 100]);
        
        $this->command->info(Artisan::output());
    }
}
