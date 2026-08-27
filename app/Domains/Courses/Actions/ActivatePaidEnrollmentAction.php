<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;

/**
 * Phase 4: flip a paid enrollment live once its money is real (webhook or
 * wallet debit). Mirrors the legacy webhook semantics: payment confirmed
 * always; status goes active only when the course does not require admin
 * approval. Idempotent.
 */
class ActivatePaidEnrollmentAction
{
    public function execute(int $enrollmentId, ?int $paymentId = null): CourseEnrollment
    {
        $enrollment = CourseEnrollment::query()->findOrFail($enrollmentId);
        $enrollment->payment_status = 'confirmed';
        if ($paymentId !== null && $enrollment->payment_id === null) {
            $enrollment->payment_id = $paymentId;
        }

        $requiresApproval = (bool) (Course::query()
            ->whereKey($enrollment->course_id)
            ->value('requires_admin_approval') ?? false);
        if (! $requiresApproval && $enrollment->status !== 'completed') {
            $enrollment->status = 'active';
            $enrollment->enrolled_at = $enrollment->enrolled_at ?? now();
        }
        $enrollment->save();

        return $enrollment->refresh();
    }
}
