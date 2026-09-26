<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\Checkout\DecideBankTransferSlipAction;
use App\Domains\Bookshop\Actions\Checkout\ServeBankTransferSlipAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\BankTransferSlip;
use App\Domains\Bookshop\Models\BookshopCheckout;

/**
 * The shop reads a bank-transfer slip and confirms or rejects it
 * (BOOKSHOP_PLAN §4: "until the vendor or the office confirms"). Only for a
 * checkout that is this shop's alone — a basket split across shops paid one
 * transfer to Akuru, so only the office can confirm it. The shop may read a
 * slip on any checkout it has an order in (plan §10 "Security").
 */
class ConfirmVendorSlipAction
{
    public function decide(VendorScope $scope, int $slipId, bool $confirm, ?string $note): BankTransferSlip
    {
        $slip = BankTransferSlip::query()->whereKey($slipId)
            ->whereHas('checkout.orders', fn ($q) => $q->where('vendor_id', $scope->vendorId))
            ->firstOrFail();
        $others = BookshopCheckout::query()->whereKey($slip->bookshop_checkout_id)
            ->whereHas('orders', fn ($q) => $q->where('vendor_id', '!=', $scope->vendorId))
            ->exists();
        abort_if($others, 403, __('shop.error_slip_not_yours'));

        return app(DecideBankTransferSlipAction::class)->execute($slip->id, $confirm, $scope->userId, $note);
    }

    /**
     * @return array{contents: string, mime: string, original_name: string}|null
     */
    public function read(VendorScope $scope, int $slipId): ?array
    {
        return app(ServeBankTransferSlipAction::class)->execute($slipId, $scope->userId, false, $scope->vendorId);
    }
}
