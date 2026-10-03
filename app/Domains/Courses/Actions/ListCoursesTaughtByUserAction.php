<?php

namespace App\Domains\Courses\Actions;

use Illuminate\Support\Facades\DB;

/**
 * The courses a signed-in person teaches (C16 slice N6; OWNER_ACTIONS 16):
 * `course_instructor` through the instructor profile linked to their login.
 * Two tables and a join rather than a model import, because the profile is
 * HR's and the login Identity's (rule 3).
 *
 * An empty list is an answer, not an error: a teacher with no assignment
 * sees an empty queue and is told why.
 */
class ListCoursesTaughtByUserAction
{
    /**
     * @return list<int>
     */
    public function execute(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return DB::table('course_instructor')
            ->join('instructors', 'instructors.id', '=', 'course_instructor.instructor_id')
            ->where('instructors.user_id', $userId)
            ->orderBy('course_instructor.course_id')
            ->pluck('course_instructor.course_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
