<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\Identity\Actions\FindUserIdByVerifiedMobileAction;
use App\Domains\People\Actions\RecordConsentAction;
use App\Domains\People\Actions\ResolveConsentPersonForUserAction;
use Illuminate\Support\Facades\Log;

class HonorPrayerUnsubscribeKeywordAction
{
    public function execute(string $phone, string $keyword, int $grantedBy = 0): int
    {
        if (! $this->isStopKeyword($keyword)) {
            return 0;
        }

        $userId = app(FindUserIdByVerifiedMobileAction::class)->execute($phone);
        if ($userId === null) {
            Log::info('Prayer SMS opt-out: no verified mobile match', ['keyword' => 'STOP']);

            return 0;
        }

        $person = app(ResolveConsentPersonForUserAction::class)->execute($userId);
        if ($person === null) {
            Log::info('Prayer SMS opt-out: no consent person', ['user_id' => $userId]);

            return 0;
        }

        app(RecordConsentAction::class)->execute(
            $person['person_type'],
            $person['person_id'],
            'prayer_reminders',
            false,
            $grantedBy > 0 ? $grantedBy : $userId,
            'sms_keyword',
        );

        Log::info('Prayer SMS opt-out honored', [
            'user_id' => $userId,
            'person_type' => $person['person_type'],
            'person_id' => $person['person_id'],
        ]);

        return 1;
    }

    private function isStopKeyword(string $keyword): bool
    {
        $parts = preg_split('/\s+/', strtoupper(trim($keyword))) ?: [];
        $first = $parts[0] ?? '';

        return in_array($first, ['STOP', 'UNSUBSCRIBE'], true);
    }
}
