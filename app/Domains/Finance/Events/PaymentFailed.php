<?php

namespace App\Domains\Finance\Events;

use App\Domains\Finance\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * B11b (BACKLOG B11, STATUS §5ua): the bank said a payment failed, was
 * declined or cancelled. Fired once, inside the transaction that marks the
 * payment failed; domains that sold something tell whoever needs to know.
 * Nothing is granted or revoked here — money and access move on
 * PaymentConfirmed and PaymentRefunded only (rule 12).
 */
class PaymentFailed
{
    use Dispatchable;

    public function __construct(public Payment $payment) {}
}
