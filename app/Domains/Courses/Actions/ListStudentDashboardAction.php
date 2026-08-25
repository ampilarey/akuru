<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Offerings\Actions\ListUpcomingSessionsForOfferingsAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Domains\Progress\Actions\ListLessonProgressAction;

class ListStudentDashboardAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $userId): array
    {
        $student = app(ResolveStudentForUserAction::class)->execute($userId);

        if ($student === null) {
            return [
                'student' => null,
                'enrollments' => [],
                'upcoming_sessions' => [],
            ];
        }

        $enrollments = CourseEnrollment::query()
            ->where('unified_student_id', $student['id'])
            ->whereIn('status', ['active', 'approved', 'completed'])
            ->orderByDesc('enrolled_at')
            ->get();

        return [
            'student' => $student,
            'upcoming_sessions' => app(ListUpcomingSessionsForOfferingsAction::class)->execute(
                $enrollments->pluck('course_offering_id')->filter()->all(),
            ),
            'enrollments' => $enrollments->map(function (CourseEnrollment $enrollment) {
                $course = Course::query()->find($enrollment->course_id);
                $progress = app(ListLessonProgressAction::class)->execute($enrollment->id);
                $completedIds = collect($progress)->where('status', 'completed')->pluck('lesson_id')->all();
                $continue = Lesson::query()
                    ->where('course_id', $enrollment->course_id)
                    ->whereNotNull('current_revision_id')
                    ->with('module')
                    ->get()
                    ->sortBy(fn (Lesson $lesson) => sprintf('%05d-%05d', $lesson->module?->position ?? 0, $lesson->position))
                    ->first(fn (Lesson $lesson) => ! in_array($lesson->id, $completedIds, true));

                return [
                    'id' => $enrollment->id,
                    'course_id' => $enrollment->course_id,
                    'title' => $course?->title ?? 'Course',
                    'status' => $enrollment->status,
                    'progress_percentage' => (int) $enrollment->progress_percentage,
                    'completed_lessons' => count($completedIds),
                    'continue_lesson_id' => $continue?->id,
                    'continue_title' => $continue?->title,
                ];
            })->values()->all(),
        ];
    }
}
