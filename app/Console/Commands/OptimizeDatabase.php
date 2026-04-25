<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\ActivityLog;

class OptimizeDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:db-optimize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize SQLite database size and performance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting database optimization...');

        // 1. Prune Activity Logs (keep only last 7 days)
        $this->info('Pruning old activity logs...');
        $deletedLogs = ActivityLog::where('created_at', '<', now()->subDays(7))->delete();
        $this->info("Deleted $deletedLogs old logs.");

        // 2. Clear expired personal access tokens
        $this->info('Clearing expired tokens...');
        DB::table('personal_access_tokens')->where('expires_at', '<', now())->delete();

        // 3. Prune sessions (keep only active)
        if (Schema::hasTable('sessions')) {
            $this->info('Pruning old sessions...');
            DB::table('sessions')->where('last_activity', '<', now()->subDays(1)->getTimestamp())->delete();
        }

        // 4. Prune cache (expired)
        if (Schema::hasTable('cache')) {
            $this->info('Pruning expired cache...');
            DB::table('cache')->where('expiration', '<', now()->getTimestamp())->delete();
        }

        // 5. SQLite Specific Optimizations
        if (DB::getDriverName() === 'sqlite') {
            $this->info('Running SQLite VACUUM...');
            DB::statement('VACUUM');
            
            $this->info('Setting Journal Mode to WAL for performance...');
            DB::statement('PRAGMA journal_mode = WAL');
            
            $this->info('Setting synchronous to NORMAL...');
            DB::statement('PRAGMA synchronous = NORMAL');
        }

        $this->info('Database optimized successfully!');
    }
}
