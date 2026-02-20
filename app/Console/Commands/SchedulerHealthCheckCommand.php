<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Called by the scheduler every minute.
 * Writes a heartbeat timestamp to cache so we can detect if the cron is running.
 */
class SchedulerHealthCheckCommand extends Command
{
    protected $signature = 'akuru:scheduler-heartbeat';
    protected $description = 'Write scheduler heartbeat to cache (called every minute by scheduler)';

    public function handle(): int
    {
        Cache::put('scheduler_heartbeat', now()->toIso8601String(), now()->addMinutes(5));
        return 0;
    }
}
