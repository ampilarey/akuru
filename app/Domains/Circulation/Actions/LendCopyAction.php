<?php

namespace App\Domains\Circulation\Actions;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\Circulation\Enums\CopyStatus;
use App\Domains\Circulation\Enums\LoanStatus;
use App\Domains\Circulation\Models\BookCopy;
use App\Domains\Circulation\Models\Loan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lend one copy to one borrower. **The only writer that creates a loan**
 * (rule 11), so a copy cannot be issued twice by two routes.
 *
 * The lend and the copy's status change happen in one transaction, with the
 * copy row locked. A circulation desk at the start of term has two people
 * scanning at once, and "available" read a moment before another desk issued
 * it is precisely how a book ends up on two borrowers' records.
 *
 * Borrower is a student **or** a member of staff, never both and never
 * neither — the two nullable columns are only as safe as the one action that
 * writes them.
 */
class LendCopyAction
{
    public function execute(
        int $copyId,
        int $issuedBy,
        ?int $studentId = null,
        ?int $borrowerUserId = null,
        ?string $note = null,
    ): Loan {
        if (($studentId === null) === ($borrowerUserId === null)) {
            throw ValidationException::withMessages([
                'borrower' => 'Choose exactly one borrower — a pupil or a member of staff.',
            ]);
        }

        $yearId = (int) (app(ResolveAcademicYearForDateAction::class)->execute()['id'] ?? 0);

        if ($yearId === 0) {
            throw ValidationException::withMessages([
                'borrower' => 'No academic year is active, so there is nothing to file this loan against.',
            ]);
        }

        return DB::transaction(function () use ($copyId, $issuedBy, $studentId, $borrowerUserId, $note, $yearId): Loan {
            /** @var BookCopy|null $copy */
            $copy = BookCopy::query()->lockForUpdate()->find($copyId);

            if ($copy === null) {
                throw ValidationException::withMessages(['copy' => 'No copy with that accession number.']);
            }

            if (! $copy->status->isLendable()) {
                throw ValidationException::withMessages([
                    'copy' => match ($copy->status) {
                        CopyStatus::OnLoan => 'That copy is already out. Take it back in first.',
                        CopyStatus::Lost => 'That copy is marked lost.',
                        default => 'That copy has been withdrawn from the collection.',
                    },
                ]);
            }

            $loanDays = (int) ($copy->title?->loan_days ?? 14);
            $today = now();

            $loan = Loan::query()->create([
                'academic_year_id' => $yearId,
                'book_copy_id' => (int) $copy->id,
                'student_id' => $studentId,
                'borrower_user_id' => $borrowerUserId,
                'out_on' => $today->toDateString(),
                'due_on' => $today->copy()->addDays(max(1, $loanDays))->toDateString(),
                'issued_by' => $issuedBy,
                'status' => LoanStatus::Out->value,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            ]);

            $copy->update(['status' => CopyStatus::OnLoan->value]);

            return $loan;
        });
    }
}
