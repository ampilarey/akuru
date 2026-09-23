<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Offerings\Actions\DefaultSelfLearningOfferingAction;
use App\Domains\Offerings\Actions\ListOpenIntakesAction;

class ListPublishedCoursesAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(?int $unifiedStudentId = null): array
    {
        $enrollments = $unifiedStudentId
            ? CourseEnrollment::query()
                ->where('unified_student_id', $unifiedStudentId)
                ->whereIn('status', ['active', 'approved', 'completed'])
                ->get(['course_id', 'progress_percentage', 'course_offering_id'])
                ->keyBy('course_id')
            : collect();

        $courses = Course::query()
            ->where('workflow_status', CourseWorkflowStatus::Published)
            ->orderBy('title')
            ->get();

        // 1B: the intakes a learner can choose between (STATUS §5fh).
        $intakes = app(ListOpenIntakesAction::class)->execute($courses->pluck('id')->map(fn ($id) => (int) $id)->all());

        return $courses
            ->map(function (Course $course) use ($enrollments, $intakes) {
                // P4.4: the default self-learning offering may override the
                // course price (0 = free offering of a paid course).
                $override = app(DefaultSelfLearningOfferingAction::class)
                    ->execute((int) $course->id)['price_override'] ?? null;
                $courseFee = (float) ($course->registration_fee_amount ?: $course->fee ?: 0);
                $enrollment = $enrollments->get($course->id);

                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'short_desc' => $course->short_desc,
                    'enrolled' => $enrollment !== null,
                    'enrolled_offering_id' => $enrollment?->course_offering_id,
                    'progress_percentage' => (int) ($enrollment->progress_percentage ?? 0),
                    // Phase 4: the same money the legacy checkout charges.
                    'fee' => $override !== null ? (float) $override : $courseFee,
                    // Each intake's own price: its override, else the course's.
                    'intakes' => array_map(fn (array $intake): array => $intake + [
                        'fee' => $intake['price_override'] !== null ? (float) $intake['price_override'] : $courseFee,
                    ], $intakes->get($course->id, [])),
                ];
            })
            ->all();
    }
}
