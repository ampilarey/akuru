<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;

/**
 * Engine-owned seam (F5-P2): live enrollments filtered by a course_type
 * VALUE the caller supplies. The engine stays subject-ignorant (rule 6) —
 * 'hifz' is data here, passed in by the Quran component; components get
 * their assignable roster without touching CourseEnrollment (rule 3).
 */
class ListEnrollmentTargetsByCourseTypeAction
{
    /**
     * @return list<array{enrollment_id: int, student_id: int, course_id: int, course_offering_id: int|null}>
     */
    public function execute(string $courseType): array
    {
        return CourseEnrollment::query()
            ->whereIn('status', ['active', 'approved'])
            ->whereHas('course', fn ($query) => $query->where('course_type', $courseType))
            ->orderBy('id')
            ->get()
            ->map(fn (CourseEnrollment $enrollment): array => [
                'enrollment_id' => $enrollment->id,
                'student_id' => (int) $enrollment->unified_student_id,
                'course_id' => (int) $enrollment->course_id,
                'course_offering_id' => $enrollment->course_offering_id ? (int) $enrollment->course_offering_id : null,
            ])
            ->values()
            ->all();
    }
}
