<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Draft;

class CreateDraftIndexes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mongodb:index-drafts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create MongoDB indexes for the Drafts collection';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Creating MongoDB indexes for Drafts collection...');

        try {
            Draft::createIndexes();
            $this->info('Draft indexes created successfully.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to create indexes: ' . $e->getMessage());
            return 1;
        }
    }
}
