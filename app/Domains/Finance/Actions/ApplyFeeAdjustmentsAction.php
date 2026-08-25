<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\Invoice;

/**
 * School-fee adjustments (sibling / scholarship / waiver) are applied here
 * at generation time — not Commerce discount codes (those live in L4).
 * S4.5 fills the percent-then-fixed math; this slice only recalculates totals.
 */
class ApplyFeeAdjustmentsAction
{
    public function execute(Invoice $invoice): Invoice
    {
        return app(RecalculateInvoiceTotalsAction::class)->execute($invoice);
    }
}
