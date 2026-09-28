<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\People\Actions\ResolveStudentForUserAction;

/**
 * Whether a login has learning of its own: a student record it owns with a
 * course enrolment that was not refused or ended (docs/SIGN_IN_PLAN.md,
 * ID2a). That is what the *My learning* workspace is held for — a parent
 * who enrols in a course, a teacher taking one, an adult who registered on
 * the website — derived rather than granted as a role, so nobody has to
 * remember to give it and nothing has to be backfilled.
 *
 * The student record is the one `/learn` reads (`ResolveStudentForUserAction`,
 * the lowest id), so the workspace is held exactly when its home has
 * something to show, pending enrolments included.
 */
class HoldsOwnLearningAction
{
    public function execute(int $userId): bool
    {
        $student = app(ResolveStudentForUserAction::class)->execute($userId);
        if ($student === null) {
            return false;
        }

        return CourseEnrollment::query()
            ->where('unified_student_id', $student['id'])
            ->whereNotIn('status', ['rejected', 'cancelled', 'withdrawn'])
            ->exists();
    }
}
