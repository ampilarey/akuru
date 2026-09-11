<?php

namespace App\Domains\Circulation\Actions;

use App\Domains\Circulation\Models\BookCopy;
use App\Domains\Circulation\Models\Loan;

/**
 * Take a whole class's textbooks back in at the end of term.
 *
 * The mirror of `BulkIssueTextbooksAction`, and partial in the same way: a
 * pupil who has lost their copy must not block the rest of the class being
 * cleared. What is left outstanding is returned to the caller, which is the
 * list the librarian chases.
 */
class BulkReturnTextbooksAction
{
    /**
     * @param  list<int>  $studentIds
     * @return array{returned: list<array{student_id: int, accession_number: string}>, outstanding: list<int>}
     */
    public function execute(int $titleId, array $studentIds, int $receivedBy): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));

        $return = app(ReturnCopyAction::class);
        $returned = [];
        $outstanding = [];

        foreach ($ids as $studentId) {
            $loan = Loan::query()
                ->out()
                ->where('student_id', $studentId)
                ->whereIn('book_copy_id', BookCopy::query()->where('book_title_id', $titleId)->select('id'))
                ->first();

            if ($loan === null) {
                $outstanding[] = $studentId;

                continue;
            }

            $accession = (string) BookCopy::query()->whereKey($loan->book_copy_id)->value('accession_number');

            $return->execute($accession, $receivedBy);
            $returned[] = ['student_id' => $studentId, 'accession_number' => $accession];
        }

        return ['returned' => $returned, 'outstanding' => $outstanding];
    }
}
