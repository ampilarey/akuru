<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\Enums\PrayerBroadcastMode;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastStatus;
use App\Domains\PrayerTimes\Models\PrayerBroadcast;
use Carbon\Carbon;

class RunChangeOnlyPrayerReminderAction
{
    public function execute(?Carbon $today = null): int
    {
        $today = $today ?? now();
        $ran = 0;

        $broadcasts = PrayerBroadcast::query()
            ->where('mode', PrayerBroadcastMode::ChangeOnly)
            ->where('status', PrayerBroadcastStatus::Queued)
            ->whereNotNull('preview_snapshot')
            ->get();

        foreach ($broadcasts as $broadcast) {
            app(EvaluateChangeOnlyPrayerBroadcastAction::class)->execute($broadcast, $today);
            $ran++;
        }

        return $ran;
    }
}
