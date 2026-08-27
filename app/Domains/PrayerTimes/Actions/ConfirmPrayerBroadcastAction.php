<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\Enums\PrayerBroadcastMode;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastStatus;
use App\Domains\PrayerTimes\Jobs\RunChangeOnlyPrayerReminderJob;
use App\Domains\PrayerTimes\Jobs\SendPrayerBroadcastJob;
use App\Domains\PrayerTimes\Models\PrayerBroadcast;
use InvalidArgumentException;

class ConfirmPrayerBroadcastAction
{
    public function execute(PrayerBroadcast $broadcast, int $confirmedBy): PrayerBroadcast
    {
        if ($broadcast->status !== PrayerBroadcastStatus::Previewed) {
            throw new InvalidArgumentException('Broadcast must be previewed before confirm.');
        }

        $snapshot = $broadcast->preview_snapshot;
        if (! is_array($snapshot) || ! array_key_exists('included_count', $snapshot)) {
            throw new InvalidArgumentException('Broadcast must be previewed before confirm.');
        }

        if (($snapshot['needs_split'] ?? false) === true) {
            throw new InvalidArgumentException('Range times change within the selected dates. Split into suggested blocks before sending.');
        }

        if ((int) ($snapshot['included_count'] ?? 0) < 1) {
            throw new InvalidArgumentException('No consented recipients to send.');
        }

        $date = ($broadcast->date_from ?? now())->toDateString();
        $broadcast->idempotency_key = $broadcast->idempotency_key
            ?: sprintf('%s:%s:%d:%d', $broadcast->mode->value, $date, $broadcast->island_id, $broadcast->id);
        $broadcast->status = PrayerBroadcastStatus::Queued;
        $broadcast->confirmed_by = $confirmedBy;
        $broadcast->save();

        if ($broadcast->mode === PrayerBroadcastMode::ChangeOnly) {
            RunChangeOnlyPrayerReminderJob::dispatch($broadcast->id);
        } else {
            SendPrayerBroadcastJob::dispatch($broadcast->id);
        }

        return $broadcast->fresh();
    }
}
