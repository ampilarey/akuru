<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Events\InvoiceIssued;
use App\Domains\Finance\Models\Invoice;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Illuminate\Support\Collection;

class IssueInvoicesAction
{
    /**
     * @param  list<int>  $invoiceIds
     * @return Collection<int, Invoice>
     */
    public function execute(array $invoiceIds): Collection
    {
        $invoices = Invoice::query()
            ->whereIn('id', $invoiceIds)
            ->where('status', InvoiceStatus::Draft->value)
            ->get();

        $names = app(ListStudentsByIdsAction::class)
            ->execute($invoices->pluck('student_id')->all())
            ->keyBy('id');

        foreach ($invoices as $invoice) {
            $invoice->status = InvoiceStatus::Sent;
            $invoice->sent_at = now('Indian/Maldives');
            $invoice->issue_date = now('Indian/Maldives')->toDateString();
            $invoice->save();

            $name = $names[$invoice->student_id]['name'] ?? (string) $invoice->notes;
            event(new InvoiceIssued(
                $invoice->id,
                $invoice->student_id,
                $name,
                $invoice->invoice_number,
                (string) $invoice->total_amount,
                $invoice->due_date?->toDateString() ?? '',
            ));
        }

        return $invoices->fresh();
    }
}
