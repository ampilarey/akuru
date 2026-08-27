<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\Enums\PrayerBroadcastRecipientStatus;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastStatus;
use App\Domains\PrayerTimes\Models\PrayerBroadcast;
use App\Domains\PrayerTimes\Models\PrayerBroadcastRecipient;
use App\Domains\Settings\Actions\GetSettingAction;
use InvalidArgumentException;

class PreviewPrayerBroadcastAction
{
    public function execute(PrayerBroadcast $broadcast): PrayerBroadcast
    {
        if ($broadcast->status === PrayerBroadcastStatus::Cancelled) {
            throw new InvalidArgumentException('Cancelled broadcasts cannot be previewed.');
        }

        $islandId = (int) $broadcast->island_id;
        $mode = $broadcast->mode->value;
        $resolver = app(ResolvePrayerTimesForIslandAction::class);
        $from = $broadcast->date_from?->copy() ?? now();
        $to = $broadcast->date_to?->copy() ?? $from->copy();

        $comparison = null;
        $needsSplit = false;
        if ($mode === 'range') {
            $comparison = app(ComparePrayerTimesAcrossRangeAction::class)->execute($islandId, $from, $to);
            $needsSplit = ! $comparison->isUniform();
        }

        $times = $resolver->execute($islandId, $from);
        $messages = app(BuildPrayerSmsMessageAction::class)->execute(
            $times,
            $broadcast->language->value,
            $broadcast->message_template ?? [],
            $from,
            $to,
        );

        $filtered = app(FilterConsentedRecipientsAction::class)->execute(
            $broadcast->recipient_group_id ? (int) $broadcast->recipient_group_id : null,
            $broadcast->recipient_refs,
        );

        $tariff = (float) app(GetSettingAction::class)->execute('prayer.sms_tariff_mvr', 0.40);
        $count = count($filtered['included']);
        $cost = round($count * $tariff, 2);

        $broadcast->preview_snapshot = [
            'included_count' => $count,
            'excluded_count' => count($filtered['excluded']),
            'excluded' => $filtered['excluded'],
            'included' => $filtered['included'],
            'messages' => $messages,
            'estimated_cost' => $cost,
            'tariff_mvr' => $tariff,
            'needs_split' => $needsSplit,
            'range' => $comparison?->toArray(),
            'times' => $times->toArray(),
            'previewed_at' => now()->toIso8601String(),
        ];
        $broadcast->estimated_cost = $cost;
        $broadcast->status = PrayerBroadcastStatus::Previewed;
        $broadcast->save();

        PrayerBroadcastRecipient::query()->where('prayer_broadcast_id', $broadcast->id)->delete();
        foreach ($filtered['included'] as $ref) {
            PrayerBroadcastRecipient::query()->create([
                'prayer_broadcast_id' => $broadcast->id,
                'contact_ref' => $ref,
                'phone' => $ref['phone'],
                'status' => PrayerBroadcastRecipientStatus::Pending,
                'message_body' => $messages['primary'],
                'cost' => $tariff,
            ]);
        }

        return $broadcast->fresh(['recipients']);
    }
}
