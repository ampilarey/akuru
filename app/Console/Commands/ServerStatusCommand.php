<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class ServerStatusCommand extends Command
{
    protected $signature = 'akuru:status';
    protected $description = 'Check scheduler, queue worker, and database health';

    public function handle(): int
    {
        $this->info('Akuru Institute — Server Status');
        $this->line(str_repeat('─', 50));

        // --- Database ---
        try {
            DB::connection()->getPdo();
            $this->line('✓  Database          connected');
        } catch (\Throwable) {
            $this->error('✗  Database          FAILED');
        }

        // --- Scheduler heartbeat ---
        $heartbeat = Cache::get('scheduler_heartbeat');
        if ($heartbeat) {
            $ago = now()->diffForHumans(\Illuminate\Support\Carbon::parse($heartbeat), true);
            $this->line("✓  Scheduler         last ran {$ago} ago ({$heartbeat})");
        } else {
            $this->warn('✗  Scheduler         NO HEARTBEAT — cron may not be configured');
            $this->line('   Add to crontab: * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1');
        }

        // --- Queue ---
        try {
            $size = Queue::size();
            $this->line("✓  Queue             {$size} job(s) pending");
        } catch (\Throwable) {
            $this->warn('⚠  Queue             could not read queue size');
        }

        // Check for recently failed jobs
        try {
            $failed = DB::table('failed_jobs')->count();
            if ($failed > 0) {
                $this->warn("⚠  Failed jobs       {$failed} failed job(s) in queue — run: php artisan queue:failed");
            } else {
                $this->line('✓  Failed jobs       none');
            }
        } catch (\Throwable) {
            $this->line('   Failed jobs table not available');
        }

        $this->line('');
        $this->comment('Queue worker command:  php artisan queue:work --sleep=3 --tries=3 --max-time=3600');
        $this->comment('Supervisor config:     see /etc/supervisor/conf.d/akuru-worker.conf');
        $this->line('');

        return 0;
    }
}
