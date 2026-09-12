<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\BankStatementMatchStatus;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Models\BankStatementLine;
use App\Domains\Finance\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * Proposes an invoice for each unmatched credit. **Proposes** is the whole
 * contract: nothing here writes money, changes an invoice, or grants access to
 * anything (rule 12). A suggestion is a hint for the person who then decides.
 *
 * Two strategies, in order of how much they actually prove:
 *
 *  1. **The invoice number appears in the line's own text.** A family typing
 *     the reference is the bank telling you which invoice this is, and it is
 *     the only evidence here that is not circumstantial.
 *  2. **The amount matches exactly one open invoice's outstanding balance.**
 *     Weaker, and deliberately refuses to guess when more than one invoice
 *     fits: two families owing the same termly fee is the normal case, not an
 *     edge case, and picking one of them at random would put money on the wrong
 *     child. Ambiguity is left unmatched with a note saying why.
 */
class SuggestBankStatementMatchesAction
{
    /**
     * @return array{suggested: int, ambiguous: int}
     */
    public function execute(?int $importId = null): array
    {
        $lines = BankStatementLine::query()
            ->when($importId, fn ($q) => $q->where('bank_statement_import_id', $importId))
            ->where('match_status', BankStatementMatchStatus::Unmatched->value)
            ->where('amount', '>', 0)
            ->get();

        if ($lines->isEmpty()) {
            return ['suggested' => 0, 'ambiguous' => 0];
        }

        $open = $this->openInvoices();
        $suggested = 0;
        $ambiguous = 0;

        foreach ($lines as $line) {
            $byNumber = $this->matchByInvoiceNumber($line, $open);
            if ($byNumber !== null) {
                $this->suggest($line, $byNumber, 'Invoice number '.$byNumber->invoice_number.' found in the statement text.');
                $suggested++;

                continue;
            }

            $candidates = $this->matchByAmount($line, $open);
            if ($candidates->count() === 1) {
                $invoice = $candidates->first();
                $this->suggest($line, $invoice, 'Amount matches the outstanding balance of '.$invoice->invoice_number.'.');
                $suggested++;

                continue;
            }

            if ($candidates->count() > 1) {
                // Recorded rather than silently skipped: "nothing matched" and
                // "several matched" are different problems for whoever reviews
                // this, and only one of them means go and find the reference.
                $line->match_note = $candidates->count().' invoices share this outstanding balance ('
                    .$candidates->take(4)->pluck('invoice_number')->implode(', ')
                    .'). Left unmatched on purpose — pick one by hand.';
                $line->save();
                $ambiguous++;
            }
        }

        return ['suggested' => $suggested, 'ambiguous' => $ambiguous];
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function openInvoices(): Collection
    {
        return Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Sent->value,
                InvoiceStatus::Overdue->value,
                InvoiceStatus::Draft->value,
            ])
            ->whereColumn('paid_amount', '<', 'total_amount')
            ->get();
    }

    /**
     * @param  Collection<int, Invoice>  $open
     */
    private function matchByInvoiceNumber(BankStatementLine $line, Collection $open): ?Invoice
    {
        $haystack = strtoupper(trim(($line->reference ?? '').' '.($line->description ?? '')));
        if ($haystack === '') {
            return null;
        }

        return $open->first(function (Invoice $invoice) use ($haystack): bool {
            $number = strtoupper(trim((string) $invoice->invoice_number));

            return $number !== '' && str_contains($haystack, $number);
        });
    }

    /**
     * @param  Collection<int, Invoice>  $open
     * @return Collection<int, Invoice>
     */
    private function matchByAmount(BankStatementLine $line, Collection $open): Collection
    {
        $tolerance = (float) config('finance.bank_statement.match_tolerance', 0);
        $amount = (float) $line->amount;

        return $open->filter(function (Invoice $invoice) use ($amount, $tolerance): bool {
            $balance = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);

            return abs($balance - $amount) <= $tolerance;
        })->values();
    }

    private function suggest(BankStatementLine $line, Invoice $invoice, string $why): void
    {
        $line->matched_invoice_id = $invoice->id;
        $line->match_status = BankStatementMatchStatus::Suggested;
        $line->match_note = $why;
        $line->save();
    }
}
