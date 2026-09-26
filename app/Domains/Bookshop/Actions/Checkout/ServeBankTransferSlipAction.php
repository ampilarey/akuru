<?php

namespace App\Domains\Bookshop\Actions\Checkout;

use App\Domains\Bookshop\Models\BankTransferSlip;
use App\Domains\Media\Actions\ReadPrivateMediaAction;

/**
 * The slip's bytes, to the paying customer, the shops with an order under
 * the checkout, or the office — and to nobody else (plan §10 "Security":
 * slips are private media). The id comes from the route, so this carries
 * the scope itself.
 */
class ServeBankTransferSlipAction
{
    /**
     * @param  ?int  $vendorId  the reading shop's id, from its `VendorScope` (B3)
     * @return array{contents: string, mime: string, original_name: string}|null
     */
    public function execute(int $slipId, int $userId, bool $isOffice, ?int $vendorId = null): ?array
    {
        $slip = BankTransferSlip::query()->with('checkout.orders')->find($slipId);
        if ($slip === null) {
            return null;
        }
        $customer = (int) $slip->checkout->user_id === $userId;
        $shop = $vendorId !== null && $slip->checkout->orders->contains('vendor_id', $vendorId);
        if (! $isOffice && ! $customer && ! $shop) {
            return null;
        }

        $read = app(ReadPrivateMediaAction::class)->execute((int) $slip->media_file_id);

        return $read === null ? null : ['contents' => $read['contents'], 'mime' => $read['mime'], 'original_name' => $read['original_name']];
    }
}
