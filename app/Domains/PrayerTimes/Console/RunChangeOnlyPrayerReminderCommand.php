<?php

namespace App\Domains\PrayerTimes\Console;

use App\Domains\PrayerTimes\Actions\RunChangeOnlyPrayerReminderAction;
use Illuminate\Console\Command;

class RunChangeOnlyPrayerReminderCommand extends Command
{
    protected $signature = 'prayer:run-change-only';

    protected $description = 'Send queued change-only prayer SMS when tomorrow differs from today';

    public function handle(RunChangeOnlyPrayerReminderAction $action): int
    {
        $count = $action->execute();
        $this->info("Evaluated {$count} change-only prayer broadcast(s).");

        return self::SUCCESS;
    }
}
