<?php

namespace App\Domains\Website\Actions;

use App\Domains\Identity\Actions\FindUserIdByVerifiedMobileAction;
use App\Domains\Website\Enums\DailySubscriptionChannel;
use App\Domains\Website\Enums\DailySubscriptionStatus;
use App\Domains\Website\Enums\DailyUnsubscribeReason;
use App\Domains\Website\Models\DailyContentSubscription;
use Illuminate\Support\Facades\Log;

class HonorDailyUnsubscribeKeywordAction
{
    public function execute(string $phone, string $keyword): int
    {
        if (! $this->isStopKeyword($keyword)) {
            return 0;
        }

        $userId = app(FindUserIdByVerifiedMobileAction::class)->execute($phone);
        if ($userId === null) {
            Log::info('Daily content SMS opt-out: no verified mobile match', [
                'keyword' => 'STOP',
            ]);

            return 0;
        }

        $rows = DailyContentSubscription::query()
            ->where('user_id', $userId)
            ->where('channel', DailySubscriptionChannel::Sms)
            ->where('status', DailySubscriptionStatus::Active)
            ->get();

        $paused = 0;
        $unsubscriber = app(UnsubscribeDailyContentSubscriptionAction::class);
        foreach ($rows as $row) {
            $unsubscriber->pause($row, DailyUnsubscribeReason::Keyword);
            $paused++;
        }

        Log::info('Daily content SMS opt-out honored', [
            'user_id' => $userId,
            'paused' => $paused,
            'reason' => DailyUnsubscribeReason::Keyword->value,
        ]);

        return $paused;
    }

    private function isStopKeyword(string $keyword): bool
    {
        $parts = preg_split('/\s+/', strtoupper(trim($keyword))) ?: [];
        $first = $parts[0] ?? '';

        return in_array($first, ['STOP', 'UNSUBSCRIBE'], true);
    }
}
