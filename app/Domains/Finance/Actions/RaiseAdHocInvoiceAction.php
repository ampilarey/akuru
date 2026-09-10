<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Enums\InvoiceType;
use App\Domains\Finance\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One invoice for one thing that is not a fee structure — a trip, a club, a
 * replacement book.
 *
 * Lives in Finance because **rule 11 says one invoice system**: a sign-up sheet
 * that grew its own money tables would be a second one. Callers hand over a
 * student, an amount and a description; everything about how an invoice is
 * shaped stays here.
 *
 * `meta.source` records what raised it, so an invoice can always be traced back
 * to the thing that caused it rather than appearing from nowhere on a family's
 * statement.
 */
class RaiseAdHocInvoiceAction
{
    /**
     * @param  array{source?: ?string, source_id?: ?int}  $meta
     */
    public function execute(
        int $studentId,
        float $amount,
        string $description,
        int $createdBy,
        ?string $dueDate = null,
        ?int $academicYearId = null,
        array $meta = [],
    ): Invoice {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'An invoice needs an amount above zero.',
            ]);
        }

        return DB::transaction(function () use ($studentId, $amount, $description, $createdBy, $dueDate, $academicYearId, $meta): Invoice {
            $invoice = Invoice::query()->create([
                // Unique per invoice without depending on a fee structure or a
                // period key, neither of which exists for a one-off.
                'invoice_number' => 'ADH-'.$studentId.'-'.now('Indian/Maldives')->format('YmdHis').'-'.random_int(100, 999),
                'student_id' => $studentId,
                'academic_year_id' => $academicYearId,
                'invoice_type' => InvoiceType::Other,
                'issue_date' => now('Indian/Maldives')->toDateString(),
                'due_date' => $dueDate ?? now('Indian/Maldives')->addDays(14)->toDateString(),
                // Issued, not draft: a family cannot pay what was never sent,
                // and the whole point of raising it is that somebody owes it.
                'status' => InvoiceStatus::Sent,
                'subtotal' => $amount,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $amount,
                'paid_amount' => 0,
                'notes' => $description,
                'meta' => [
                    'source' => $meta['source'] ?? 'ad_hoc',
                    'source_id' => $meta['source_id'] ?? null,
                ],
                'created_by' => $createdBy,
                'sent_at' => now(),
            ]);

            $invoice->lines()->create([
                'description' => $description,
                'quantity' => 1,
                'unit_price' => $amount,
                'line_total' => $amount,
            ]);

            return $invoice->fresh(['lines']);
        });
    }
}
