<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\Receipt;
use Illuminate\Support\Collection;

class ListReconciliationAction
{
    /**
     * @return array{rows: Collection<int, array<string, mixed>>, daily: Collection<int, array<string, mixed>>}
     */
    public function execute(?string $from = null, ?string $to = null): array
    {
        $receipts = Receipt::query()
            ->when($from, fn ($q) => $q->whereDate('received_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('received_at', '<=', $to))
            ->orderBy('received_at')
            ->get();

        $invoiceIds = $receipts->pluck('invoice_id')->all();
        $invoices = Invoice::query()->whereIn('id', $invoiceIds)->get()->keyBy('id');
        $payments = Payment::query()->whereIn('id', $receipts->pluck('payment_id')->filter())->get()->keyBy('id');

        $rows = $receipts->map(function (Receipt $receipt) use ($invoices, $payments) {
            $invoice = $invoices[$receipt->invoice_id] ?? null;
            $payment = $receipt->payment_id ? ($payments[$receipt->payment_id] ?? null) : null;
            $invoiceBalance = $invoice
                ? number_format((float) $invoice->total_amount - (float) $invoice->paid_amount, 2, '.', '')
                : null;

            return [
                'receipt_number' => $receipt->receipt_number,
                'payment_reference' => $payment?->merchant_reference,
                'invoice_number' => $invoice?->invoice_number,
                'method' => $receipt->method?->value,
                'amount' => $receipt->amount,
                'received_at' => $receipt->received_at?->toDateTimeString(),
                'invoice_balance' => $invoiceBalance,
            ];
        });

        $daily = $receipts->groupBy(fn (Receipt $row) => $row->received_at?->toDateString().'|'.($row->method?->value ?? 'unknown'))
            ->map(function (Collection $group, string $key) {
                [$date, $method] = explode('|', $key, 2);

                return [
                    'date' => $date,
                    'method' => $method,
                    'total' => number_format($group->sum(fn (Receipt $row) => (float) $row->amount), 2, '.', ''),
                    'count' => $group->count(),
                ];
            })
            ->values();

        return ['rows' => $rows, 'daily' => $daily];
    }
}
