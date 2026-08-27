<?php

namespace App\Domains\Library\Listeners;

use App\Domains\Commerce\Actions\RecordDiscountRedemptionAction;
use App\Domains\Finance\Events\PaymentRefunded;
use App\Domains\Library\Models\LibraryAccessGrant;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Library\Models\WriterEarning;

/**
 * P4.3: the mirror of GrantLibraryAccessOnPaymentConfirmed — a FULL
 * refund revokes the purchase-sourced grant (LIBRARY_PLAN §24 / rule
 * §43.10 "admin can revoke access in special cases"). Partial refunds
 * keep access.
 */
class RevokeLibraryAccessOnPaymentRefunded
{
    public function handle(PaymentRefunded $event): void
    {
        $payment = $event->payment;
        if (! $event->fullyRefunded
            || $payment->getRawOriginal('payable_type') !== 'library_item'
            || $payment->payable_id === null) {
            return;
        }

        $purchase = LibraryPurchase::query()
            ->where('payment_id', $payment->id)
            ->first();
        if ($purchase !== null) {
            $purchase->status = 'refunded';
            $purchase->save();
            app(RecordDiscountRedemptionAction::class)->releaseForRefund('library_purchase', $purchase->id);
            // L6 clawback (§43.7): the sale's earning is no longer payable.
            // An earning already PAID out is an operator reconciliation case
            // (recorded in STATUS) — it still flips so reports show it.
            WriterEarning::query()
                ->where('library_purchase_id', $purchase->id)
                ->update(['status' => 'refunded']);
        }

        LibraryAccessGrant::query()
            ->where('user_id', $payment->user_id)
            ->where('library_item_id', (int) $payment->payable_id)
            ->where('source_type', 'purchase')
            ->when($purchase !== null, fn ($query) => $query->where('source_id', $purchase->id))
            ->update(['status' => 'revoked', 'ends_at' => now()]);
    }
}
