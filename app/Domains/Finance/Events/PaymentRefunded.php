<?php

namespace App\Domains\Finance\Events;

use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\PaymentRefund;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * P4.3: fired inside the refund transaction each time a refund is
 * recorded. Domains revoke what the money bought by listening here —
 * the mirror of PaymentConfirmed. fullyRefunded is true only when the
 * refund total has reached the payment amount; partial refunds keep
 * access.
 */
class PaymentRefunded
{
    use Dispatchable;

    public function __construct(
        public Payment $payment,
        public PaymentRefund $refund,
        public bool $fullyRefunded,
    ) {}
}
