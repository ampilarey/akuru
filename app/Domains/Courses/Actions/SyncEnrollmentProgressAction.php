<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Offerings\Actions\ListRequiredSessionProgressAction;
use App\Domains\Progress\Actions\EvaluateCourseCompletionAction;
use App\Domains\Progress\Actions\ListLessonProgressAction;

class SyncEnrollmentProgressAction
{
    public function execute(CourseEnrollment $enrollment): CourseEnrollment
    {
        $required = app(AuthorizeLessonAccessAction::class)->requiredLessons($enrollment->course_id);
        $completed = collect(app(ListLessonProgressAction::class)->execute($enrollment->id))
            ->where('status', 'completed')
            ->pluck('lesson_id')
            ->all();

        $sessions = $enrollment->course_offering_id
            ? app(ListRequiredSessionProgressAction::class)->execute(
                (int) $enrollment->course_offering_id,
                $enrollment->id,
            )
            : ['required_session_ids' => [], 'attended_session_ids' => []];

        $result = app(EvaluateCourseCompletionAction::class)->execute(
            array_map(fn ($lesson) => $lesson->id, $required),
            $completed,
            $sessions['required_session_ids'],
            $sessions['attended_session_ids'],
        );

        $enrollment->progress_percentage = $result['percentage'];
        if ($result['is_complete']) {
            $enrollment->status = 'completed';
            $enrollment->completed_at = $enrollment->completed_at ?? now();
        }
        $enrollment->save();

        return $enrollment->refresh();
    }
}
