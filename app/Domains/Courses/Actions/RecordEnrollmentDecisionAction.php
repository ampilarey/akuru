<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;

/**
 * Stamp who made a decision about an enrolment, and when (STATUS §5ih).
 *
 * The five decisions — activate, reject, suspend, reinstate, the access
 * window — call this from their Actions with the actor's id; a decision
 * with no actor (the payment webhook activating a paid place) leaves the
 * stamp alone, so an empty stamp reads as "the system, or before this
 * existed" and never as a person.
 */
class RecordEnrollmentDecisionAction
{
    public const ACTIVATED = 'activated';

    public const REJECTED = 'rejected';

    public const SUSPENDED = 'suspended';

    public const REINSTATED = 'reinstated';

    public const ACCESS_WINDOW = 'access_window';

    public function execute(CourseEnrollment $enrollment, string $decision, ?int $decidedBy): void
    {
        if ($decidedBy === null) {
            return;
        }

        $enrollment->forceFill([
            'decided_by_user_id' => $decidedBy,
            'decided_at' => now(),
            'decision' => $decision,
        ])->save();
    }
}
