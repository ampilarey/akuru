<?php

namespace App\Domains\PrayerTimes\Jobs;

use App\Domains\PrayerTimes\Actions\EvaluateChangeOnlyPrayerBroadcastAction;
use App\Domains\PrayerTimes\Actions\RunChangeOnlyPrayerReminderAction;
use App\Domains\PrayerTimes\Models\PrayerBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunChangeOnlyPrayerReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $broadcastId = null) {}

    public function handle(): void
    {
        if ($this->broadcastId) {
            $broadcast = PrayerBroadcast::query()->find($this->broadcastId);
            if ($broadcast === null) {
                return;
            }
            app(EvaluateChangeOnlyPrayerBroadcastAction::class)->execute($broadcast);

            return;
        }

        app(RunChangeOnlyPrayerReminderAction::class)->execute();
    }
}
