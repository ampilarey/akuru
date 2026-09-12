<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\BankStatementMatchStatus;
use App\Domains\Finance\Enums\ReceiptMethod;
use App\Domains\Finance\Models\BankStatementLine;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A person agrees that a bank credit pays an invoice, and **only then** does
 * money move.
 *
 * Rule 12 shapes every line of this:
 *
 *  - The receipt is written by `RecordInvoiceReceiptAction` — the same audited
 *    path a cashier uses, which allocates, numbers and renders the document.
 *    Nothing here touches `invoices.paid_amount` directly.
 *  - The method is `transfer`, never `bml`. A statement line is a bank telling
 *    you money arrived; it is not a gateway webhook, and recording it as one
 *    would make the reconciliation report lie about how the school was paid.
 *  - It grants access to nothing. Enrolment in a paid course still waits on
 *    BML webhook confirmation, which this cannot and must not substitute for.
 *  - Confirming twice is refused rather than duplicated, because the second
 *    receipt would be real money against a real invoice.
 */
class ConfirmBankStatementMatchAction
{
    public function execute(BankStatementLine $line, int $confirmedBy, ?int $invoiceId = null): Receipt
    {
        if ($line->match_status === BankStatementMatchStatus::Confirmed) {
            throw ValidationException::withMessages([
                'line' => 'This line was already confirmed as receipt '
                    .(Receipt::query()->find($line->receipt_id)?->receipt_number ?? '—').'.',
            ]);
        }

        if (! $line->isCredit()) {
            throw ValidationException::withMessages([
                'line' => 'Only money coming in can pay an invoice. This line is a debit.',
            ]);
        }

        $invoice = Invoice::query()->find($invoiceId ?? $line->matched_invoice_id);
        if ($invoice === null) {
            throw ValidationException::withMessages(['invoice_id' => 'Choose an invoice for this line.']);
        }

        $balance = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
        if ($balance <= 0) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Invoice '.$invoice->invoice_number.' has nothing outstanding.',
            ]);
        }

        // An overpayment is credited only up to the balance. The rest is left
        // on the statement line for a human to place, rather than silently
        // parked on one invoice — a family paying two invoices with one
        // transfer is common, and guessing which one gets the surplus is how
        // money ends up on the wrong child.
        $amount = min(round((float) $line->amount, 2), $balance);

        return DB::transaction(function () use ($line, $invoice, $amount, $confirmedBy) {
            $receipt = app(RecordInvoiceReceiptAction::class)->execute([
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'method' => ReceiptMethod::Transfer->value,
                'received_by' => $confirmedBy,
                'received_at' => $line->posted_on?->copy()->setTime(12, 0) ?? now('Indian/Maldives'),
            ]);

            $line->forceFill([
                'match_status' => BankStatementMatchStatus::Confirmed->value,
                'matched_invoice_id' => $invoice->id,
                'receipt_id' => $receipt->id,
                'decided_by' => $confirmedBy,
                'decided_at' => now('Indian/Maldives'),
                'match_note' => $amount < (float) $line->amount
                    ? 'Confirmed '.number_format($amount, 2, '.', '').' of '
                        .number_format((float) $line->amount, 2, '.', '')
                        .' against '.$invoice->invoice_number.'; the remainder is unplaced.'
                    : 'Confirmed against '.$invoice->invoice_number.'.',
            ])->save();

            return $receipt;
        });
    }
}
