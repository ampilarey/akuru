<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\Enums\PrayerBroadcastRecipientStatus;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastStatus;
use App\Domains\PrayerTimes\Models\PrayerBroadcast;
use App\Domains\PrayerTimes\Models\PrayerBroadcastRecipient;
use Carbon\Carbon;

class EvaluateChangeOnlyPrayerBroadcastAction
{
    public function execute(PrayerBroadcast $broadcast, ?Carbon $today = null): PrayerBroadcast
    {
        $today = ($today ?? now())->copy()->timezone(config('app.timezone'))->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $islandId = (int) $broadcast->island_id;

        $resolver = app(ResolvePrayerTimesForIslandAction::class);
        $todayTimes = $resolver->execute($islandId, $today);
        $tomorrowTimes = $resolver->execute($islandId, $tomorrow);

        if ($todayTimes->available && $tomorrowTimes->available && $todayTimes->equals($tomorrowTimes)) {
            PrayerBroadcastRecipient::query()
                ->where('prayer_broadcast_id', $broadcast->id)
                ->where('status', PrayerBroadcastRecipientStatus::Pending)
                ->update([
                    'status' => PrayerBroadcastRecipientStatus::Skipped,
                    'error' => 'unchanged',
                ]);
            $broadcast->status = PrayerBroadcastStatus::Completed;
            $broadcast->save();

            return $broadcast->fresh();
        }

        return app(SendPrayerBroadcastAction::class)->execute($broadcast);
    }
}
