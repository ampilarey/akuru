<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\Invoice;

class RecalculateInvoiceTotalsAction
{
    public function execute(Invoice $invoice): Invoice
    {
        $invoice->load('lines');
        $subtotal = 0.0;
        $discount = 0.0;
        foreach ($invoice->lines as $line) {
            $subtotal += (float) $line->line_total;
            $discount += (float) $line->discount_amount;
        }

        $invoice->subtotal = round($subtotal, 2);
        $invoice->discount_amount = round($discount, 2);
        $invoice->total_amount = round($subtotal + (float) $invoice->tax_amount - $discount, 2);
        $invoice->save();

        return $invoice->refresh();
    }
}
