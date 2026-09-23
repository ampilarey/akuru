<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\PaymentPlan;
use Illuminate\Support\Collection;

class ListPortalInvoicesAction
{
    /**
     * @param  list<int>  $studentIds
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $studentIds): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if ($ids === []) {
            return collect();
        }

        $invoices = Invoice::query()->with('receipts')->whereIn('student_id', $ids)->orderByDesc('issue_date')->get();
        $plans = PaymentPlan::query()->with('installments')->whereIn('invoice_id', $invoices->pluck('id'))->get()->keyBy('invoice_id');

        return $invoices->map(function (Invoice $invoice) use ($plans) {
            $plan = $plans[$invoice->id] ?? null;
            $next = $plan?->installments->first(fn ($row) => $row->remaining() > 0);

            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                // What it is for. An ad-hoc invoice (a trip sign-up's fee)
                // carries its reason in `notes`; a generated fee invoice has
                // its type. A number alone is an invoice from nowhere on a
                // family's statement (E6c's own words; the sign-up walk,
                // STATUS §5fq, found the statement showing only the number).
                'description' => $invoice->notes ?: ($invoice->invoice_type?->value ?? null),
                'student_id' => $invoice->student_id,
                'status' => $invoice->status?->value,
                'due_date' => $invoice->due_date?->toDateString(),
                'total_amount' => $invoice->total_amount,
                'paid_amount' => $invoice->paid_amount,
                'balance' => number_format((float) $invoice->total_amount - (float) $invoice->paid_amount, 2, '.', ''),
                'plan_status' => $plan?->status?->value,
                'next_installment' => $next ? number_format($next->remaining(), 2, '.', '') : null,
                'receipts' => $invoice->receipts->map(fn ($row) => [
                    'id' => $row->id,
                    'receipt_number' => $row->receipt_number,
                    'amount' => $row->amount,
                    'method' => $row->method?->value,
                ])->values(),
            ];
        });
    }
}
