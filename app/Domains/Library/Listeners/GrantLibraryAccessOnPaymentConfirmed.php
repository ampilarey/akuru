<?php

namespace App\Domains\Library\Listeners;

use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Library\Actions\GrantLibraryAccessAction;
use App\Domains\Library\Models\LibraryPurchase;

/**
 * L3, business rule §43.5: access to paid library content depends on the
 * BML WEBHOOK confirmation — this listener is the only path from money to
 * a grant. Idempotent: the grant firstOrCreates and the purchase flip only
 * moves pending → paid once.
 */
class GrantLibraryAccessOnPaymentConfirmed
{
    public function handle(PaymentConfirmed $event): void
    {
        $payment = $event->payment;
        if ($payment->getRawOriginal('payable_type') !== 'library_item'
            || $payment->payable_id === null
            || $payment->user_id === null) {
            return;
        }

        $purchase = LibraryPurchase::query()
            ->where('payment_id', $payment->id)
            ->first();
        if ($purchase !== null && $purchase->status === 'pending') {
            $purchase->status = 'paid';
            $purchase->purchased_at = now();
            $purchase->save();
        }

        app(GrantLibraryAccessAction::class)->execute(
            (int) $payment->user_id,
            (int) $payment->payable_id,
            'purchase',
            $purchase?->id,
        );
    }
}
