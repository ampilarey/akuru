<?php

namespace App\Domains\Finance\DTOs;

/**
 * One parsed line of a bank statement, in the shape the importer stores —
 * deliberately not the shape any particular bank exports. Adapting to a real
 * export is a parser's job, so that the rest of Finance never learns a bank's
 * column names.
 */
final class BankStatementRow
{
    public function __construct(
        public readonly string $postedOn,
        public readonly float $amount,
        public readonly ?string $description = null,
        public readonly ?string $reference = null,
        public readonly string $currency = 'MVR',
    ) {}

    /**
     * Date, amount, description and reference — everything that makes this line
     * *this* line. Used to keep a re-imported overlapping period from
     * duplicating rows, which is the normal case rather than the exception:
     * banks routinely re-export a window that covers what you already have.
     */
    public function hash(): string
    {
        return hash('sha256', implode('|', [
            $this->postedOn,
            number_format($this->amount, 2, '.', ''),
            trim((string) $this->description),
            trim((string) $this->reference),
        ]));
    }
}
