<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\Enums\PrayerBroadcastMode;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastStatus;
use App\Domains\PrayerTimes\Models\PrayerBroadcast;

class RunDailyPrayerReminderAction
{
    public function execute(?string $date = null): int
    {
        $date = $date ?: now()->timezone(config('app.timezone'))->toDateString();
        $ran = 0;

        $broadcasts = PrayerBroadcast::query()
            ->where('mode', PrayerBroadcastMode::Daily)
            ->whereIn('status', [
                PrayerBroadcastStatus::Queued,
                PrayerBroadcastStatus::Completed,
            ])
            ->whereNotNull('preview_snapshot')
            ->get();

        foreach ($broadcasts as $broadcast) {
            $key = sprintf('daily:%s:%d:%d', $date, $broadcast->island_id, $broadcast->id);
            $already = PrayerBroadcast::query()
                ->where('idempotency_key', $key)
                ->where('sent_count', '>', 0)
                ->exists();
            if ($already || ($broadcast->idempotency_key === $key && (int) $broadcast->sent_count > 0)) {
                continue;
            }

            if ($broadcast->status === PrayerBroadcastStatus::Queued) {
                app(SendPrayerBroadcastAction::class)->execute($broadcast);
                $broadcast->idempotency_key = $key;
                $broadcast->save();
                $ran++;
            }
        }

        return $ran;
    }
}
