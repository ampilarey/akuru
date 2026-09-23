<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;

/**
 * Engine-owned seam for components (F0), the plural of
 * `ResolveLatestEnrollmentIdAction`: every live enrolment a student holds,
 * by id, without handing the model across the component boundary (rule 3).
 *
 * A student's own report reads across all of them. The Arabic report read
 * only the latest, so a student who enrolled on a second course watched
 * every attempt on the first go to zero (STATUS §5fx).
 *
 * @return list<int>
 */
class ListLiveEnrollmentIdsAction
{
    public function execute(int $studentId): array
    {
        return CourseEnrollment::query()
            ->where('unified_student_id', $studentId)
            ->whereIn('status', ['active', 'approved', 'completed'])
            ->orderByDesc('enrolled_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
