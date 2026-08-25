<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Progress\Actions\CalculateCourseProgressAction;
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

        $done = 0;
        foreach ($required as $lesson) {
            if (in_array($lesson->id, $completed, true)) {
                $done++;
            }
        }

        $enrollment->progress_percentage = app(CalculateCourseProgressAction::class)->execute($done, count($required));
        if (count($required) > 0 && $done === count($required)) {
            $enrollment->status = 'completed';
            $enrollment->completed_at = $enrollment->completed_at ?? now();
        }
        $enrollment->save();

        return $enrollment->refresh();
    }
}
