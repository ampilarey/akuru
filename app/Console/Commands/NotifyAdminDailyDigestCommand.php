<?php

namespace App\Console\Commands;

use App\Domains\Academics\Actions\NotifyAdminDailyDigestAction;
use Illuminate\Console\Command;

class NotifyAdminDailyDigestCommand extends Command
{
    protected $signature = 'school:notify-daily-digest';

    protected $description = 'Send admins the daily absence and unfilled-register digest (off unless the admin_daily_digest setting is on)';

    public function handle(NotifyAdminDailyDigestAction $action): int
    {
        $count = $action->execute();
        $this->info("Sent {$count} daily digest(s).");

        return self::SUCCESS;
    }
}
