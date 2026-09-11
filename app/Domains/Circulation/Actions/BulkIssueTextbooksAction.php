<?php

namespace App\Domains\Circulation\Actions;

use App\Domains\Circulation\Models\BookCopy;
use App\Domains\Circulation\Models\Loan;
use Illuminate\Validation\ValidationException;

/**
 * Issue one title to a whole class at the start of term.
 *
 * **Partial success is the design**, not a compromise. Issuing forty textbooks
 * is forty independent facts, and a class where one pupil already holds a copy
 * must not leave the other thirty-nine unissued. The result says exactly who
 * got a book, who did not, and why — so the librarian can act on the short
 * list instead of re-running the whole class and hoping.
 *
 * Each issue still goes through `LendCopyAction` (rule 11), so a bulk issue
 * and a single one cannot drift apart.
 *
 * @see BulkReturnTextbooksAction the end-of-term mirror
 */
class BulkIssueTextbooksAction
{
    /**
     * @param  list<int>  $studentIds
     * @return array{issued: list<array{student_id: int, accession_number: string}>, skipped: list<array{student_id: int, reason: string}>}
     */
    public function execute(int $titleId, array $studentIds, int $issuedBy): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));

        if ($ids === []) {
            throw ValidationException::withMessages(['students' => 'Choose at least one pupil.']);
        }

        $lend = app(LendCopyAction::class);
        $issued = [];
        $skipped = [];

        foreach ($ids as $studentId) {
            // Somebody who already holds a copy of this title does not need a
            // second one — the commonest cause of a class being re-issued.
            $alreadyHas = Loan::query()
                ->out()
                ->where('student_id', $studentId)
                ->whereIn('book_copy_id', BookCopy::query()->where('book_title_id', $titleId)->select('id'))
                ->exists();

            if ($alreadyHas) {
                $skipped[] = ['student_id' => $studentId, 'reason' => 'already has a copy'];

                continue;
            }

            $copy = BookCopy::query()->where('book_title_id', $titleId)->available()->first();

            if ($copy === null) {
                $skipped[] = ['student_id' => $studentId, 'reason' => 'no copy left on the shelf'];

                continue;
            }

            try {
                $loan = $lend->execute((int) $copy->id, $issuedBy, studentId: $studentId);
                $issued[] = [
                    'student_id' => $studentId,
                    'accession_number' => (string) $copy->accession_number,
                ];
                unset($loan);
            } catch (ValidationException $e) {
                // Another desk took the copy between the read and the write.
                $skipped[] = [
                    'student_id' => $studentId,
                    'reason' => collect($e->errors())->flatten()->first() ?? 'could not be issued',
                ];
            }
        }

        return ['issued' => $issued, 'skipped' => $skipped];
    }
}
