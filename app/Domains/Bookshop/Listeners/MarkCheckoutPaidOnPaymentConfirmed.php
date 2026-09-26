<?php

namespace App\Domains\Bookshop\Listeners;

use App\Domains\Bookshop\Actions\Checkout\MarkCheckoutPaidAction;
use App\Domains\Finance\Events\PaymentConfirmed;

/**
 * Rule 12 / BOOKSHOP_PLAN §8: a checkout becomes paid on Finance's
 * `PaymentConfirmed` — the BML webhook for a card, or (B3) the manual
 * payment the office or shop records when it confirms a bank-transfer
 * slip — never on the return URL. Idempotent through
 * `MarkCheckoutPaidAction`, which also keeps the payment's id so a refund
 * can go back against it.
 */
class MarkCheckoutPaidOnPaymentConfirmed
{
    public function handle(PaymentConfirmed $event): void
    {
        $payment = $event->payment;
        if ($payment->getRawOriginal('payable_type') !== 'bookshop_checkout' || $payment->payable_id === null) {
            return;
        }

        $how = $payment->provider === 'manual' ? 'bank_transfer' : 'card';
        app(MarkCheckoutPaidAction::class)->execute((int) $payment->payable_id, $how, null, (int) $payment->id);
    }
}
