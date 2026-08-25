<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Models\Invoice;

class MarkOverdueInvoicesAction
{
    public function execute(?string $asOf = null): int
    {
        $today = $asOf ?? now('Indian/Maldives')->toDateString();

        return Invoice::query()
            ->where('status', InvoiceStatus::Sent->value)
            ->whereDate('due_date', '<', $today)
            ->whereColumn('paid_amount', '<', 'total_amount')
            ->update(['status' => InvoiceStatus::Overdue->value]);
    }
}
