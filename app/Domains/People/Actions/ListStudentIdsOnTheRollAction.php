<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\Student;

/**
 * Which of these student ids belong to pupils still on the roll.
 *
 * The bulk form of the question `Student::scopeOnTheRoll()` defines, for
 * callers holding a list of ids — a halaqa's enrolments, a club's membership —
 * who would otherwise ask it one row at a time.
 *
 * Returns ids rather than models so a caller can intersect without loading
 * anybody, and so the answer is cheap enough to ask on every generation run.
 *
 * `Academics\ListStudentsOnActiveRosterAction` answers a **different**
 * question — who has an active class placement in the current academic year —
 * and the two are not interchangeable: a pupil can be on the roll while
 * between class placements, and this one does not need an academic year to
 * exist before it can answer.
 */
class ListStudentIdsOnTheRollAction
{
    /**
     * @param  iterable<mixed>  $studentIds
     * @return list<int>
     */
    public function execute(iterable $studentIds): array
    {
        $ids = collect($studentIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return Student::query()
            ->onTheRoll()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
