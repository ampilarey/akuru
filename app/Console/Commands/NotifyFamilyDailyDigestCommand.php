<?php

namespace App\Console\Commands;

use App\Domains\Portal\Actions\NotifyFamilyDailyDigestAction;
use Illuminate\Console\Command;

class NotifyFamilyDailyDigestCommand extends Command
{
    protected $signature = 'family:notify-daily-digest';

    protected $description = "Send families an evening summary of tomorrow's lessons, homework due and unread notices (off unless the family_daily_digest setting is on)";

    public function handle(NotifyFamilyDailyDigestAction $action): int
    {
        $count = $action->execute();
        $this->info("Sent {$count} family digest(s).");

        return self::SUCCESS;
    }
}
