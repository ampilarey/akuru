<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Enums\DailyContentType;
use App\Domains\Website\Enums\DailySubscriptionChannel;
use App\Domains\Website\Enums\DailySubscriptionLanguage;
use App\Domains\Website\Enums\DailySubscriptionStatus;
use App\Domains\Website\Models\DailyContentSubscription;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveDailyContentSubscriptionAction
{
    /**
     * Opt-in or update one channel for this user. Never creates a row for another user.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(int $userId, array $data): DailyContentSubscription
    {
        $channel = DailySubscriptionChannel::tryFrom((string) ($data['channel'] ?? ''));
        if ($channel === null) {
            throw ValidationException::withMessages(['channel' => 'Choose SMS, email, or push.']);
        }

        $language = DailySubscriptionLanguage::tryFrom((string) ($data['language'] ?? 'en'))
            ?? DailySubscriptionLanguage::English;

        $types = $this->normalizeTypes($data['content_types'] ?? []);
        if ($types === []) {
            throw ValidationException::withMessages(['content_types' => 'Choose at least one content type.']);
        }

        $sendTime = $this->normalizeSendTime((string) ($data['send_time'] ?? '06:00'));

        $row = DailyContentSubscription::query()
            ->where('user_id', $userId)
            ->where('channel', $channel)
            ->first();

        $payload = [
            'user_id' => $userId,
            'channel' => $channel,
            'content_types' => $types,
            'language' => $language,
            'send_time' => $sendTime,
            'status' => DailySubscriptionStatus::Active,
            'unsubscribed_at' => null,
            'unsubscribe_reason' => null,
        ];

        if ($row === null) {
            $payload['unsubscribe_token'] = Str::lower(Str::random(48));

            return DailyContentSubscription::query()->create($payload);
        }

        $row->fill($payload);
        $row->save();

        return $row;
    }

    /**
     * @return list<string>
     */
    private function normalizeTypes(mixed $raw): array
    {
        $values = is_array($raw) ? $raw : (is_string($raw) && $raw !== '' ? [$raw] : []);
        $types = [];
        foreach ($values as $value) {
            $type = DailyContentType::tryFrom((string) $value);
            if ($type !== null) {
                $types[$type->value] = $type->value;
            }
        }

        $order = ['ayah', 'hadith', 'saying', 'reminder'];

        return array_values(array_filter($order, fn (string $type) => isset($types[$type])));
    }

    private function normalizeSendTime(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/^\d{2}:\d{2}$/', $raw) === 1) {
            return $raw.':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $raw) === 1) {
            return $raw;
        }

        throw ValidationException::withMessages(['send_time' => 'Send time must be HH:MM.']);
    }
}
