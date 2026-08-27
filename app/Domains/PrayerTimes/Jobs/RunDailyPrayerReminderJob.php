<?php

namespace App\Domains\PrayerTimes\Jobs;

use App\Domains\PrayerTimes\Actions\RunDailyPrayerReminderAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunDailyPrayerReminderJob implements ShouldQueue
{
    use Queueable;

    public function handle(RunDailyPrayerReminderAction $action): void
    {
        $action->execute();
    }
}
