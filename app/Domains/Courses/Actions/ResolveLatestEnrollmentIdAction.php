<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;

/**
 * Engine-owned seam for components (F0): "the student's most recent live
 * enrollment", without handing the CourseEnrollment model across the
 * component boundary (rule 3).
 */
class ResolveLatestEnrollmentIdAction
{
    public function execute(int $studentId): ?int
    {
        $id = CourseEnrollment::query()
            ->where('unified_student_id', $studentId)
            ->whereIn('status', ['active', 'approved', 'completed'])
            ->orderByDesc('enrolled_at')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
