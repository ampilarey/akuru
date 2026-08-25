<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\AssessmentStatus;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseModule;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Offerings\Actions\ListUpcomingSessionsForOfferingsAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Domains\Progress\Actions\ListLessonProgressAction;

class ListCourseLearningAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $courseId, int $userId): array
    {
        $course = Course::query()->findOrFail($courseId);
        $student = app(ResolveStudentForUserAction::class)->execute($userId);
        $enrollment = $student
            ? CourseEnrollment::query()
                ->where('course_id', $courseId)
                ->where('unified_student_id', $student['id'])
                ->whereIn('status', ['active', 'approved', 'completed'])
                ->first()
            : null;

        $progress = $enrollment
            ? collect(app(ListLessonProgressAction::class)->execute($enrollment->id))->keyBy('lesson_id')
            : collect();

        $auth = app(AuthorizeLessonAccessAction::class);
        $modules = CourseModule::query()
            ->where('course_id', $courseId)
            ->with(['lessons' => fn ($query) => $query->whereNotNull('current_revision_id')])
            ->orderBy('position')
            ->get();

        return [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'short_desc' => $course->short_desc,
            ],
            'enrollment' => $enrollment ? [
                'id' => $enrollment->id,
                'progress_percentage' => (int) $enrollment->progress_percentage,
                'status' => $enrollment->status,
            ] : null,
            'upcoming_sessions' => $enrollment?->course_offering_id
                ? app(ListUpcomingSessionsForOfferingsAction::class)->execute([(int) $enrollment->course_offering_id])
                : [],
            'activities' => app(ListCourseActivitiesAction::class)->execute($course, includeAnswerKeys: false)->values(),
            'assessments' => Assessment::query()
                ->where('course_id', $courseId)
                ->where('status', AssessmentStatus::Published)
                ->orderBy('id')
                ->get()
                ->map(fn (Assessment $assessment): array => [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'assessment_type' => $assessment->assessment_type,
                ])
                ->values(),
            'modules' => $modules->map(fn (CourseModule $module) => [
                'id' => $module->id,
                'title' => $module->title,
                'lessons' => $module->lessons->map(function (Lesson $lesson) use ($auth, $enrollment, $progress) {
                    $row = $progress->get($lesson->id);

                    return [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'is_preview' => $lesson->is_preview,
                        'status' => $row['status'] ?? 'not_started',
                        'unlocked' => $lesson->is_preview || ($enrollment !== null && $auth->isUnlocked($lesson, $enrollment->id)),
                    ];
                })->values(),
            ])->values(),
        ];
    }
}
