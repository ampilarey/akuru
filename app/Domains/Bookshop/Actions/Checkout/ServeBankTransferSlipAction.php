<?php

namespace App\Domains\Bookshop\Actions\Checkout;

use App\Domains\Bookshop\Models\BankTransferSlip;
use App\Domains\Media\Actions\ReadPrivateMediaAction;

/**
 * The slip's bytes, to the paying customer or to the office and to nobody
 * else (plan §10 "Security": slips are private media). The id comes from
 * the route, so this carries the scope itself — the customer must own the
 * checkout, or hold `bookshop.manage`.
 *
 * @return array{contents: string, mime: string, original_name: string}|null
 */
class ServeBankTransferSlipAction
{
    public function execute(int $slipId, int $userId, bool $isOffice): ?array
    {
        $slip = BankTransferSlip::query()->with('checkout')->find($slipId);
        if ($slip === null) {
            return null;
        }
        if (! $isOffice && (int) $slip->checkout->user_id !== $userId) {
            return null;
        }

        $read = app(ReadPrivateMediaAction::class)->execute((int) $slip->media_file_id);

        return $read === null ? null : ['contents' => $read['contents'], 'mime' => $read['mime'], 'original_name' => $read['original_name']];
    }
}
