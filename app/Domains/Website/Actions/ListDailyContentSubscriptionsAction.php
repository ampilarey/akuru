<?php

namespace App\Domains\Website\Actions;

use App\Domains\Identity\Actions\ReadVerifiedUserContactsAction;
use App\Domains\Website\Enums\DailyDeliveryStatus;
use App\Domains\Website\Models\DailyContentDelivery;
use App\Domains\Website\Models\DailyContentSubscription;
use Illuminate\Support\Collection;

class ListDailyContentSubscriptionsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forUser(int $userId): Collection
    {
        return DailyContentSubscription::query()
            ->where('user_id', $userId)
            ->orderBy('channel')
            ->get()
            ->map(fn (DailyContentSubscription $row) => $this->present($row))
            ->values();
    }

    /**
     * @return array{
     *     totals: array<string, int>,
     *     types: array<string, int>,
     *     failures: list<array<string, mixed>>,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function metrics(): array
    {
        $rows = DailyContentSubscription::query()->orderBy('id')->get();
        $totals = [
            'sms_active' => 0,
            'sms_paused' => 0,
            'email_active' => 0,
            'email_paused' => 0,
            'push_active' => 0,
            'push_paused' => 0,
        ];
        $types = [
            'ayah' => 0,
            'hadith' => 0,
            'saying' => 0,
            'reminder' => 0,
        ];

        $presented = [];
        foreach ($rows as $row) {
            $presented[] = $this->present($row);
            $channel = $row->channel?->value ?? (string) $row->channel;
            $status = $row->status?->value ?? (string) $row->status;
            $key = $channel.'_'.$status;
            if (array_key_exists($key, $totals)) {
                $totals[$key]++;
            }
            foreach ((array) $row->content_types as $type) {
                if (isset($types[$type])) {
                    $types[$type]++;
                }
            }
        }

        $failures = DailyContentDelivery::query()
            ->where('status', DailyDeliveryStatus::Failed)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (DailyContentDelivery $row) => [
                'id' => $row->id,
                'subscription_id' => $row->subscription_id,
                'send_date' => $row->send_date?->toDateString(),
                'channel' => $row->channel?->value ?? (string) $row->channel,
                'error' => $row->error,
            ])
            ->values()
            ->all();

        return [
            'totals' => $totals,
            'types' => $types,
            'failures' => $failures,
            'rows' => $presented,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function csvRows(): array
    {
        $metrics = $this->metrics();
        $out = [];
        foreach ($metrics['rows'] as $row) {
            $contacts = app(ReadVerifiedUserContactsAction::class)->execute((int) $row['user_id']);
            $out[] = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'channel' => $row['channel'],
                'status' => $row['status'],
                'language' => $row['language'],
                'content_types' => implode('|', $row['content_types']),
                'send_time' => $row['send_time'],
                'email' => $contacts['email'] ?? '',
                'phone' => $contacts['phone'] ?? '',
                'unsubscribed_at' => $row['unsubscribed_at'] ?? '',
                'unsubscribe_reason' => $row['unsubscribe_reason'] ?? '',
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(DailyContentSubscription $row): array
    {
        $sendTime = (string) $row->send_time;
        if (strlen($sendTime) >= 8) {
            $sendTime = substr($sendTime, 0, 8);
        }

        return [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'channel' => $row->channel?->value ?? (string) $row->channel,
            'content_types' => array_values((array) $row->content_types),
            'language' => $row->language?->value ?? (string) $row->language,
            'send_time' => $sendTime,
            'status' => $row->status?->value ?? (string) $row->status,
            'unsubscribe_token' => $row->unsubscribe_token,
            'unsubscribed_at' => $row->unsubscribed_at?->toDateTimeString(),
            'unsubscribe_reason' => $row->unsubscribe_reason?->value,
        ];
    }
}
