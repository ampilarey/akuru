<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\Enums\PrayerBroadcastLanguage;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastMode;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastStatus;
use App\Domains\PrayerTimes\Models\PrayerBroadcast;

class SavePrayerBroadcastAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?PrayerBroadcast $broadcast, int $userId): PrayerBroadcast
    {
        $mode = PrayerBroadcastMode::from((string) ($data['mode'] ?? 'daily'));
        $payload = [
            'mode' => $mode,
            'island_id' => (int) $data['island_id'],
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'created_by' => $broadcast?->created_by ?? $userId,
            'message_template' => $data['message_template'] ?? null,
            'recipient_group_id' => $data['recipient_group_id'] ?? null,
            'recipient_refs' => $this->parseRefs($data['recipient_refs'] ?? null),
            'language' => PrayerBroadcastLanguage::from((string) ($data['language'] ?? 'en')),
        ];

        if ($broadcast === null) {
            $payload['status'] = PrayerBroadcastStatus::Draft;

            return PrayerBroadcast::query()->create($payload);
        }

        if ($broadcast->status !== PrayerBroadcastStatus::Draft) {
            $broadcast->status = PrayerBroadcastStatus::Draft;
            $broadcast->preview_snapshot = null;
        }

        $broadcast->fill($payload);
        $broadcast->save();

        return $broadcast->fresh();
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function parseRefs(mixed $raw): ?array
    {
        if (is_array($raw)) {
            return array_values($raw);
        }
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $ids = preg_split('/[\s,]+/', $raw) ?: [];
            $decoded = [];
            foreach ($ids as $id) {
                if (ctype_digit($id)) {
                    $decoded[] = ['type' => 'user', 'id' => (int) $id];
                }
            }
        }

        return $decoded;
    }
}
