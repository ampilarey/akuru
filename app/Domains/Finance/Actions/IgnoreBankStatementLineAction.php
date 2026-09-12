<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\BankStatementMatchStatus;
use App\Domains\Finance\Models\BankStatementLine;
use Illuminate\Validation\ValidationException;

/**
 * Marks a line as "not a school payment" — bank charges, interest, an outgoing
 * transfer, a correction. The line itself is never deleted: rule 12's
 * append-only spirit applies to the record of what the bank said, and a
 * dismissed line still has to be explainable six months later.
 *
 * A confirmed line cannot be ignored. Undoing money is a refund, which has its
 * own action, its own event and its own audit trail.
 */
class IgnoreBankStatementLineAction
{
    public function execute(BankStatementLine $line, int $decidedBy, ?string $reason = null): BankStatementLine
    {
        if ($line->match_status === BankStatementMatchStatus::Confirmed) {
            throw ValidationException::withMessages([
                'line' => 'This line is already a receipt. Reverse it with a refund, not by ignoring it.',
            ]);
        }

        $line->forceFill([
            'match_status' => BankStatementMatchStatus::Ignored->value,
            'matched_invoice_id' => null,
            'match_note' => $reason !== null && trim($reason) !== ''
                ? trim($reason)
                : 'Not a school payment.',
            'decided_by' => $decidedBy,
            'decided_at' => now('Indian/Maldives'),
        ])->save();

        return $line->fresh();
    }
}
