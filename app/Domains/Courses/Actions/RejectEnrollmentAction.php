<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;

/**
 * Reject an enrolment. Until STATUS §5ih this was one `update()` in
 * `AdminEnrollmentController` — the one status write done in a controller
 * (admin-panel audit) — and recorded nobody. A rejected enrolment holds no
 * seat (`ReserveOfferingSeatAction`'s occupying list), so there is nothing
 * to release.
 */
class RejectEnrollmentAction
{
    public function execute(CourseEnrollment $enrollment, ?int $decidedBy = null): CourseEnrollment
    {
        $enrollment->update(['status' => 'rejected']);
        app(RecordEnrollmentDecisionAction::class)->execute($enrollment, RecordEnrollmentDecisionAction::REJECTED, $decidedBy);

        return $enrollment->refresh();
    }
}
