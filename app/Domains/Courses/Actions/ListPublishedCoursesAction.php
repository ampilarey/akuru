<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Offerings\Actions\DefaultSelfLearningOfferingAction;

class ListPublishedCoursesAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(?int $unifiedStudentId = null): array
    {
        $enrolled = $unifiedStudentId
            ? CourseEnrollment::query()
                ->where('unified_student_id', $unifiedStudentId)
                ->whereIn('status', ['active', 'approved', 'completed'])
                ->pluck('progress_percentage', 'course_id')
            : collect();

        return Course::query()
            ->where('workflow_status', CourseWorkflowStatus::Published)
            ->orderBy('title')
            ->get()
            ->map(function (Course $course) use ($enrolled) {
                // P4.4: the default self-learning offering may override the
                // course price (0 = free offering of a paid course).
                $override = app(DefaultSelfLearningOfferingAction::class)
                    ->execute((int) $course->id)['price_override'] ?? null;

                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'short_desc' => $course->short_desc,
                    'enrolled' => $enrolled->has($course->id),
                    'progress_percentage' => (int) ($enrolled[$course->id] ?? 0),
                    // Phase 4: the same money the legacy checkout charges.
                    'fee' => $override !== null
                        ? (float) $override
                        : (float) ($course->registration_fee_amount ?: $course->fee ?: 0),
                ];
            })
            ->all();
    }
}
