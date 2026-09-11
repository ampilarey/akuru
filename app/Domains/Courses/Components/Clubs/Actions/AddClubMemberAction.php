<?php

namespace App\Domains\Courses\Components\Clubs\Actions;

use App\Domains\Courses\Actions\EnrollUnifiedStudentInOfferingAction;
use App\Domains\Offerings\Actions\DefaultSelfLearningOfferingAction;
use Illuminate\Validation\ValidationException;

/**
 * Put somebody in a club.
 *
 * Writes **no enrolment logic of its own** (rule 11). It finds the club's
 * offering through Offerings' own action and hands off to the engine's
 * enroller, which already handles the duplicate case, the legacy student id
 * and the seat reservation. This action exists only to name the club-shaped
 * version of that and to give a decent error when a club has not been set up.
 *
 * Membership is not restricted to pupils on the class roll — that is the
 * plan's "members from outside the enrolled roll", and it needed no code,
 * only the absence of a check.
 */
class AddClubMemberAction
{
    public function execute(int $clubCourseId, int $unifiedStudentId, ?int $addedBy = null): int
    {
        $offering = app(DefaultSelfLearningOfferingAction::class)->execute($clubCourseId);

        if ($offering === null) {
            // Deliberately an operator-facing sentence rather than a null
            // return: "nothing happened" on a roster screen is the failure
            // mode this session has spent all day fixing.
            throw ValidationException::withMessages([
                'student_id' => 'This club has no offering yet. Create one for the club course before adding members.',
            ]);
        }

        $enrollment = app(EnrollUnifiedStudentInOfferingAction::class)
            ->execute($unifiedStudentId, $clubCourseId, (int) $offering['id'], $addedBy);

        return (int) $enrollment->id;
    }
}
