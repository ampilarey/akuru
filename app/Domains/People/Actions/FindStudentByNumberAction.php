<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Facades\DB;

/**
 * The pupil whose student number this is — what a borrower card's barcode
 * carries (slice LD1). Exact, not a search: a desk that scans a card lends to
 * that pupil or to nobody. The number is unique on `students`.
 */
class FindStudentByNumberAction
{
    public function execute(string $number): ?int
    {
        $number = trim($number);

        if ($number === '') {
            return null;
        }

        $id = DB::table('students')->where('student_id', $number)->value('id');

        return $id === null ? null : (int) $id;
    }
}
