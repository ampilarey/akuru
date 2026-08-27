<?php

namespace App\Domains\PrayerTimes\Console;

use App\Domains\PrayerTimes\Actions\RunDailyPrayerReminderAction;
use Illuminate\Console\Command;

class RunDailyPrayerReminderCommand extends Command
{
    protected $signature = 'prayer:run-daily';

    protected $description = 'Send queued daily prayer-time SMS reminders for today';

    public function handle(RunDailyPrayerReminderAction $action): int
    {
        $count = $action->execute();
        $this->info("Ran {$count} daily prayer reminder broadcast(s).");

        return self::SUCCESS;
    }
}
