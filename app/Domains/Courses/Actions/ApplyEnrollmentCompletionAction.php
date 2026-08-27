<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;

/**
 * Engine-owned seam (F2): persist an externally evaluated completion result
 * onto an enrollment. Subject-ignorant — the caller decides what "complete"
 * means (rule 6); components use this instead of touching CourseEnrollment
 * across the boundary (rule 3). Write shape mirrors
 * SyncEnrollmentProgressAction so both paths age together.
 */
class ApplyEnrollmentCompletionAction
{
    public function execute(int $enrollmentId, int $percentage, bool $isComplete): void
    {
        $enrollment = CourseEnrollment::query()->findOrFail($enrollmentId);
        $enrollment->progress_percentage = max(0, min(100, $percentage));
        if ($isComplete) {
            $enrollment->status = 'completed';
            $enrollment->completed_at = $enrollment->completed_at ?? now();
        }
        $enrollment->save();
    }
}
