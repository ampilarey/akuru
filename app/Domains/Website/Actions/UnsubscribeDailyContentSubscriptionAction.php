<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Enums\DailySubscriptionStatus;
use App\Domains\Website\Enums\DailyUnsubscribeReason;
use App\Domains\Website\Models\DailyContentSubscription;

class UnsubscribeDailyContentSubscriptionAction
{
    public function pauseOwn(int $userId, DailyContentSubscription $subscription): DailyContentSubscription
    {
        abort_unless((int) $subscription->user_id === $userId, 403);

        return $this->pause($subscription, null);
    }

    public function resumeOwn(int $userId, DailyContentSubscription $subscription): DailyContentSubscription
    {
        abort_unless((int) $subscription->user_id === $userId, 403);

        $subscription->status = DailySubscriptionStatus::Active;
        $subscription->unsubscribed_at = null;
        $subscription->unsubscribe_reason = null;
        $subscription->save();

        return $subscription;
    }

    public function executeByToken(string $token): ?DailyContentSubscription
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $row = DailyContentSubscription::query()->where('unsubscribe_token', $token)->first();
        if ($row === null) {
            return null;
        }

        return $this->pause($row, DailyUnsubscribeReason::Link);
    }

    public function pause(DailyContentSubscription $subscription, ?DailyUnsubscribeReason $reason): DailyContentSubscription
    {
        $subscription->status = DailySubscriptionStatus::Paused;
        if ($reason !== null) {
            $subscription->unsubscribed_at = now();
            $subscription->unsubscribe_reason = $reason;
        }
        $subscription->save();

        return $subscription;
    }
}
