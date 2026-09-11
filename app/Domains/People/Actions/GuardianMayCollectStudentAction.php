<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Facades\DB;

/**
 * May this guardian collect this child?
 *
 * Deliberately **not** the same question as `GuardianCanAccessStudentAction`.
 * Being allowed to read a child's attendance is not being allowed to take them
 * out of the building, and conflating the two is how a child leaves with the
 * wrong adult.
 *
 * Reads `guardian_student.can_pickup` on the unified link table. That column
 * has existed since the unification and, until now, **nothing ever read it** —
 * `SaveStudentAction` and `AttachGuardianAction` write it and default it to
 * true. E8 is its first reader, which means the flag starts meaning something
 * the day this ships: a school that has been clearing it for a guardian will
 * find that decision suddenly enforced, and a school that never touched it
 * gets the permissive default it already had.
 */
class GuardianMayCollectStudentAction
{
    public function execute(int $guardianUserId, int $studentId): bool
    {
        $guardianIds = DB::table('parent_guardians')
            ->where('user_id', $guardianUserId)
            ->pluck('id');

        if ($guardianIds->isEmpty()) {
            return false;
        }

        return DB::table('guardian_student')
            ->whereIn('guardian_id', $guardianIds)
            ->where('student_id', $studentId)
            ->where('can_pickup', true)
            ->exists();
    }
}
