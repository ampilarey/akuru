<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Notifications\Actions\SendUserNotificationAction;
use App\Support\Said;

/**
 * BP3 (BACKLOG B11): a large wallet credit made by hand is told to every
 * other commerce manager in the app, so no one adds big money unseen. The
 * one who made it is not told; below the threshold nobody is.
 */
class AlertLargeWalletCreditAction
{
    /** @return int how many managers were told */
    public function execute(int $walletUserId, float $amount, int $actorId): int
    {
        if ($amount < (float) config('commerce.wallet.alert_credit_at', 1000)) {
            return 0;
        }
        $userModel = config('auth.providers.users.model');
        try {
            $ids = $userModel::query()->permission('commerce.manage')->whereKeyNot($actorId)->pluck('id');
        } catch (\Throwable) {
            return 0;
        }
        $actor = $userModel::query()->find($actorId)?->name ?? '—';
        $owner = $userModel::query()->find($walletUserId)?->name ?? '#'.$walletUserId;
        $told = 0;
        foreach ($ids as $id) {
            $told += app(SendUserNotificationAction::class)->execute((int) $id, Said::of('admin.commerce_alert_credit_title'), Said::of('admin.commerce_alert_credit_body', [
                'actor' => $actor, 'amount' => number_format($amount, 2), 'owner' => $owner,
            ]), ['category' => 'finance', 'href' => '/admin/commerce']) ? 1 : 0;
        }

        return $told;
    }
}
