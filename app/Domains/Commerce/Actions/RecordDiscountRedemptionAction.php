<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\DiscountRedemption;

/**
 * A redemption is recorded PENDING at checkout, CONFIRMED when the payment
 * confirms (webhook / wallet debit), RELEASED if the payment fails — so
 * usage limits never leak from abandoned checkouts forever, and confirmed
 * usage is never lost.
 */
class RecordDiscountRedemptionAction
{
    public function execute(
        int $discountCodeId,
        int $userId,
        string $purchaseType,
        ?int $purchaseId,
        float $amountDiscounted,
    ): DiscountRedemption {
        return DiscountRedemption::query()->create([
            'discount_code_id' => $discountCodeId,
            'user_id' => $userId,
            'purchase_type' => $purchaseType,
            'purchase_id' => $purchaseId,
            'amount_discounted' => round($amountDiscounted, 2),
            'status' => 'pending',
        ]);
    }

    public function transition(string $purchaseType, int $purchaseId, string $status): int
    {
        return DiscountRedemption::query()
            ->where('purchase_type', $purchaseType)
            ->where('purchase_id', $purchaseId)
            ->where('status', 'pending')
            ->update(['status' => $status]);
    }
}
