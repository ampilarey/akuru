<?php

namespace App\Domains\Website\Actions;

use App\Domains\Identity\Actions\ReadVerifiedUserContactsAction;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Website\Enums\DailyDeliveryStatus;
use App\Domains\Website\Enums\DailySubscriptionChannel;
use App\Domains\Website\Enums\DailySubscriptionStatus;
use App\Domains\Website\Mail\DailyContentDigestMail;
use App\Domains\Website\Models\DailyContentDelivery;
use App\Domains\Website\Models\DailyContentSubscription;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverDailyContentSubscriptionsAction
{
    public function execute(?string $date = null): int
    {
        $now = now()->timezone(config('app.timezone'));
        $sendDate = $date ?? $now->toDateString();
        $nowTime = $now->format('H:i:s');
        $sent = 0;

        $subscriptions = DailyContentSubscription::query()
            ->where('status', DailySubscriptionStatus::Active)
            ->whereIn('channel', [
                DailySubscriptionChannel::Sms->value,
                DailySubscriptionChannel::Email->value,
            ])
            ->where('send_time', '<=', $nowTime)
            ->whereDoesntHave('deliveries', function ($query) use ($sendDate): void {
                $query->whereDate('send_date', $sendDate);
            })
            ->orderBy('id')
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($this->deliverOne($subscription, $sendDate)) {
                $sent++;
            }
        }

        return $sent;
    }

    private function deliverOne(DailyContentSubscription $subscription, string $sendDate): bool
    {
        $types = array_values(array_filter(
            is_array($subscription->content_types) ? $subscription->content_types : [],
            fn ($type) => is_string($type) && $type !== '',
        ));
        if ($types === []) {
            return false;
        }

        $items = app(ListPublicDailyContentsAction::class)->publishedOnDate($sendDate, $types);
        if ($items === []) {
            return false;
        }

        $channel = $subscription->channel instanceof DailySubscriptionChannel
            ? $subscription->channel
            : DailySubscriptionChannel::tryFrom((string) $subscription->channel);
        if ($channel === null || $channel === DailySubscriptionChannel::Push) {
            return false;
        }

        $language = $subscription->language?->value ?? 'en';
        $message = app(ComposeDailySubscriptionMessageAction::class)->execute(
            $items,
            $language,
            (string) $subscription->unsubscribe_token,
        );

        $contacts = app(ReadVerifiedUserContactsAction::class)->execute((int) $subscription->user_id);

        try {
            if ($channel === DailySubscriptionChannel::Sms) {
                $phone = $contacts['phone'] ?? null;
                if ($phone === null || $phone === '') {
                    $this->record($subscription, $sendDate, DailyDeliveryStatus::Failed, 'no verified mobile');

                    return false;
                }

                app(SmsSenderInterface::class)->sendSms($phone, $message['sms'], [
                    'type' => 'daily_content',
                    'reference' => 'daily-sub-'.$subscription->id,
                ]);
            } else {
                $email = $contacts['email'] ?? null;
                if ($email === null || $email === '') {
                    $this->record($subscription, $sendDate, DailyDeliveryStatus::Failed, 'no verified email');

                    return false;
                }

                Mail::to($email)->send(new DailyContentDigestMail(
                    subjectLine: $message['subject'],
                    items: $message['items'],
                    unsubscribeUrl: $message['unsubscribe_url'],
                    language: $language,
                ));
            }

            $this->record($subscription, $sendDate, DailyDeliveryStatus::Sent, null);

            return true;
        } catch (Throwable $e) {
            $this->record($subscription, $sendDate, DailyDeliveryStatus::Failed, $e->getMessage());

            return false;
        }
    }

    private function record(
        DailyContentSubscription $subscription,
        string $sendDate,
        DailyDeliveryStatus $status,
        ?string $error,
    ): void {
        try {
            DailyContentDelivery::query()->create([
                'subscription_id' => $subscription->id,
                'send_date' => $sendDate,
                'channel' => $subscription->channel,
                'status' => $status,
                'error' => $error,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another worker already claimed this subscription+day.
        }
    }
}
