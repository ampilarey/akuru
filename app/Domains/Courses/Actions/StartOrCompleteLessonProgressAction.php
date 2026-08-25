<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Progress\Actions\RecordLessonProgressAction;
use Illuminate\Contracts\Auth\Authenticatable;

class StartOrCompleteLessonProgressAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $lessonId, ?Authenticatable $user, string $status = 'in_progress'): array
    {
        $access = app(AuthorizeLessonAccessAction::class)->execute($lessonId, $user);
        $lesson = $access['lesson'];
        $enrollment = $access['enrollment'];

        if ($enrollment === null || $lesson->current_revision_id === null) {
            return ['recorded' => false];
        }

        $recorded = app(RecordLessonProgressAction::class)->execute([
            'enrollment_id' => $enrollment->id,
            'course_id' => $lesson->course_id,
            'course_module_id' => $lesson->course_module_id,
            'lesson_id' => $lesson->id,
            'lesson_revision_id' => $lesson->current_revision_id,
            'student_id' => $enrollment->unified_student_id,
            'status' => $status,
        ]);

        app(SyncEnrollmentProgressAction::class)->execute($enrollment->fresh());

        return $recorded + ['recorded' => true];
    }
}
