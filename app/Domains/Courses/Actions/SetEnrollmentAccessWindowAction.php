<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;

/**
 * Save an enrolment's access window (SPEC §11.7) and record who set it.
 * Validation and normalisation stay in `ResolveEnrollmentAccessWindowAction`;
 * this is the write, moved out of the controller with the stamp (§5ih).
 *
 * @param  array<string, mixed>  $input  the raw `access_starts_at` / `access_ends_at`
 */
class SetEnrollmentAccessWindowAction
{
    public function execute(CourseEnrollment $enrollment, array $input, ?int $decidedBy = null): CourseEnrollment
    {
        $enrollment->update(app(ResolveEnrollmentAccessWindowAction::class)->validated($input));
        app(RecordEnrollmentDecisionAction::class)->execute($enrollment, RecordEnrollmentDecisionAction::ACCESS_WINDOW, $decidedBy);

        return $enrollment->refresh();
    }
}
