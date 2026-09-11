<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;

/**
 * Engine-owned seam: end one enrollment.
 *
 * The mirror of `EnrollUnifiedStudentInOfferingAction`. Components need a way
 * to remove a member — a club roster changes every term — and rule 3 forbids
 * them touching `CourseEnrollment` themselves, so the engine owns the verb.
 *
 * Cancels rather than deletes. An enrollment is the record that somebody was
 * in a club last term; deleting the row would quietly rewrite that, and the
 * status column already exists to say it ended.
 *
 * Returns false when there was nothing to cancel, so a caller can tell "already
 * gone" from "just removed" without a second query.
 */
class CancelEnrollmentAction
{
    public function execute(int $enrollmentId): bool
    {
        $enrollment = CourseEnrollment::query()
            ->whereKey($enrollmentId)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->first();

        if ($enrollment === null) {
            return false;
        }

        $enrollment->update(['status' => 'cancelled']);

        return true;
    }
}
