<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\Payment;

/**
 * Money received, as the administrator's home shows it. Paid payments only:
 * a pending or failed BML attempt is not revenue (rule 12).
 */
class SumPaidPaymentsAction
{
    /** Paid today, in the ledger's unit (MVR). */
    public function today(): float
    {
        return (float) Payment::query()->where('status', 'paid')->whereDate('created_at', today())->sum('amount');
    }

    /** Paid in all. */
    public function total(): float
    {
        return (float) Payment::query()->where('status', 'paid')->sum('amount');
    }
}
