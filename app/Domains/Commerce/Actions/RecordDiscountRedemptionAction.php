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

    /**
     * Give back the slots held by purchases that never happened.
     *
     * `'released'` is the status this class's own docblock describes — *"so
     * usage limits never leak from abandoned checkouts forever"* — and until
     * now **nothing ever wrote it**. `transition()` was only ever called with
     * `'confirmed'`, and there is no payment-failed event at all, so a
     * redemption recorded at checkout stayed `pending` for good.
     *
     * `ResolveDiscountAction` counts pending **and** confirmed against both
     * `usage_limit` and `per_user_limit`. So a family who opened checkout and
     * closed the tab burned their only use of the code, and a code limited to
     * 100 uses ran out after 100 attempts rather than 100 purchases.
     *
     * Called from `akuru:prune-expired`, which is where abandoned enrolments
     * are already cleaned up.
     *
     * @param  list<int>  $purchaseIds
     */
    public function releaseAbandoned(string $purchaseType, array $purchaseIds): int
    {
        if ($purchaseIds === []) {
            return 0;
        }

        return DiscountRedemption::query()
            ->where('purchase_type', $purchaseType)
            ->whereIn('purchase_id', $purchaseIds)
            // Only pending. A confirmed redemption means the payment landed,
            // and releasing that would hand back a slot the customer used.
            ->where('status', 'pending')
            ->update(['status' => 'released']);
    }

    /**
     * P4.3: a fully refunded purchase gives its usage slot back — pending
     * AND confirmed redemptions release, since the customer kept nothing.
     */
    public function releaseForRefund(string $purchaseType, int $purchaseId): int
    {
        return DiscountRedemption::query()
            ->where('purchase_type', $purchaseType)
            ->where('purchase_id', $purchaseId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->update(['status' => 'released']);
    }
}
