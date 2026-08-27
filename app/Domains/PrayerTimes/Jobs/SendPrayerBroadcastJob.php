<?php

namespace App\Domains\PrayerTimes\Jobs;

use App\Domains\PrayerTimes\Actions\SendPrayerBroadcastAction;
use App\Domains\PrayerTimes\Models\PrayerBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPrayerBroadcastJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $broadcastId) {}

    public function handle(SendPrayerBroadcastAction $action): void
    {
        $broadcast = PrayerBroadcast::query()->find($this->broadcastId);
        if ($broadcast === null) {
            return;
        }

        $action->execute($broadcast);
    }
}
