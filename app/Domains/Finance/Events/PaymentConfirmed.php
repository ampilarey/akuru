<?php

namespace App\Domains\Finance\Events;

use App\Domains\Finance\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once when a payment reaches confirmed via a VERIFIED provider
 * result (webhook or authoritative status query) — never from the return
 * URL. Other domains grant access by listening here (rule 3: cross-domain
 * via Events), instead of PaymentService learning their models. Since P4.2
 * every confirmation path fires this — there is no inline activation left.
 */
class PaymentConfirmed
{
    use Dispatchable;

    public function __construct(public Payment $payment) {}
}
