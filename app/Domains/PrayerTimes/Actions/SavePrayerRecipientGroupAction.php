<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\Models\PrayerRecipientGroup;

class SavePrayerRecipientGroupAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?PrayerRecipientGroup $group, int $userId): PrayerRecipientGroup
    {
        $payload = [
            'name_en' => (string) ($data['name_en'] ?? ''),
            'name_dv' => (string) ($data['name_dv'] ?? ''),
            'name_ar' => (string) ($data['name_ar'] ?? ''),
            'description' => $data['description'] ?? null,
            'member_refs' => $this->parseRefs($data['member_refs'] ?? null),
            'is_active' => array_key_exists('is_active', $data)
                ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)
                : true,
        ];

        if ($group === null) {
            $payload['created_by'] = $userId;

            return PrayerRecipientGroup::query()->create($payload);
        }

        $group->fill($payload);
        $group->save();

        return $group->fresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseRefs(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values($raw);
        }
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values($decoded);
        }

        $ids = preg_split('/[\s,]+/', $raw) ?: [];
        $refs = [];
        foreach ($ids as $id) {
            if (ctype_digit($id)) {
                $refs[] = ['type' => 'user', 'id' => (int) $id];
            }
        }

        return $refs;
    }
}
