<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Support\PersonName;

class ListGuardianLearningAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $guardianUserId): array
    {
        $children = app(ListGuardianChildrenAction::class)->executeForGuardianUserId($guardianUserId);

        return [
            'children' => $children->map(function (object $child) {
                $enrollments = CourseEnrollment::query()
                    ->where('unified_student_id', $child->id)
                    ->whereIn('status', ['active', 'approved', 'completed'])
                    ->orderByDesc('enrolled_at')
                    ->get()
                    ->map(function (CourseEnrollment $enrollment) {
                        $course = Course::query()->find($enrollment->course_id);

                        return [
                            'id' => $enrollment->id,
                            'course_id' => $enrollment->course_id,
                            'title' => $course?->title ?? 'Course',
                            'status' => $enrollment->status,
                            'progress_percentage' => (int) $enrollment->progress_percentage,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => (int) $child->id,
                    'name' => PersonName::ofStudent($child),
                    'relationship' => $child->relationship,
                    'enrollments' => $enrollments,
                ];
            })->values()->all(),
        ];
    }
}
