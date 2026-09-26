<?php

namespace App\Domains\Bookshop\Listeners;

use App\Domains\Bookshop\Actions\Checkout\MarkCheckoutPaidAction;
use App\Domains\Finance\Events\PaymentConfirmed;

/**
 * Rule 12 / BOOKSHOP_PLAN §8: a card checkout becomes paid on the BML
 * webhook's `PaymentConfirmed`, never on the return URL. Idempotent
 * through `MarkCheckoutPaidAction`.
 */
class MarkCheckoutPaidOnPaymentConfirmed
{
    public function handle(PaymentConfirmed $event): void
    {
        $payment = $event->payment;
        if ($payment->getRawOriginal('payable_type') !== 'bookshop_checkout' || $payment->payable_id === null) {
            return;
        }

        app(MarkCheckoutPaidAction::class)->execute((int) $payment->payable_id, 'card');
    }
}
