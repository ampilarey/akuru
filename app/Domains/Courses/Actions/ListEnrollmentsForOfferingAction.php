<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;

class ListEnrollmentsForOfferingAction
{
    /**
     * @return list<array{id: int, student_id: int, status: string}>
     */
    public function execute(int $offeringId): array
    {
        return CourseEnrollment::query()
            ->where('course_offering_id', $offeringId)
            ->whereIn('status', ['active', 'approved', 'completed'])
            ->orderBy('id')
            ->get()
            ->map(fn (CourseEnrollment $enrollment) => [
                'id' => $enrollment->id,
                'student_id' => (int) $enrollment->unified_student_id,
                'status' => $enrollment->status,
            ])
            ->all();
    }
}
