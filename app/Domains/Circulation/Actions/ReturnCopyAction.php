<?php

namespace App\Domains\Circulation\Actions;

use App\Domains\Circulation\Enums\CopyStatus;
use App\Domains\Circulation\Enums\LoanStatus;
use App\Domains\Circulation\Models\BookCopy;
use App\Domains\Circulation\Models\Loan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Take a copy back in, by accession number rather than by loan id.
 *
 * **That is the whole ergonomics of a return desk**: somebody hands over a
 * book, you scan the label. Nobody knows which loan row it is, and asking them
 * to find one is how a return desk stops being used.
 *
 * Marking a copy lost closes its loan the same way, so a lost book never sits
 * open on a borrower's record forever.
 */
class ReturnCopyAction
{
    public function execute(string $accessionNumber, int $receivedBy, bool $lost = false): Loan
    {
        return DB::transaction(function () use ($accessionNumber, $receivedBy, $lost): Loan {
            /** @var BookCopy|null $copy */
            $copy = BookCopy::query()
                ->lockForUpdate()
                ->where('accession_number', trim($accessionNumber))
                ->first();

            if ($copy === null) {
                throw ValidationException::withMessages(['copy' => 'No copy with that accession number.']);
            }

            /** @var Loan|null $loan */
            $loan = Loan::query()->out()->where('book_copy_id', $copy->id)->latest('out_on')->first();

            if ($loan === null) {
                throw ValidationException::withMessages([
                    'copy' => 'That copy is not out on loan — nothing to take back.',
                ]);
            }

            $loan->update([
                'returned_on' => now()->toDateString(),
                'received_by' => $receivedBy,
                'status' => ($lost ? LoanStatus::Lost : LoanStatus::Returned)->value,
            ]);

            $copy->update(['status' => ($lost ? CopyStatus::Lost : CopyStatus::Available)->value]);

            return $loan->refresh();
        });
    }
}
