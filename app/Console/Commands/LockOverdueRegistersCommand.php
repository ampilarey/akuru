<?php

namespace App\Console\Commands;

use App\Domains\Academics\Actions\LockOverdueRegistersAction;
use Illuminate\Console\Command;

class LockOverdueRegistersCommand extends Command
{
    protected $signature = 'registers:lock-overdue {--days= : Override register_lock_days}';

    protected $description = 'Lock class registers whose lesson date is older than the lock window';

    public function handle(LockOverdueRegistersAction $action): int
    {
        $days = $this->option('days') !== null && $this->option('days') !== ''
            ? (int) $this->option('days')
            : null;
        $locked = $action->execute($days);
        $this->info("Locked {$locked} registers.");

        return self::SUCCESS;
    }
}
