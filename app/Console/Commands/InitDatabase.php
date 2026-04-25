<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Exception;

#[Signature('init-database')]
#[Description('Initialize the database by checking connection and running migrations')]
class InitDatabase extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Connecting to database...');

        try {
            DB::connection()->getPdo();
        } catch (Exception $e) {
            $this->error('Database connection failed: ' . $e->getMessage());
            return 1;
        }

        $this->info('Running migrations...');

        try {
            Artisan::call('migrate', ['--force' => true], $this->getOutput());
            $this->info('Database initialized successfully');
        } catch (Exception $e) {
            $this->error('Failed to run migrations: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
