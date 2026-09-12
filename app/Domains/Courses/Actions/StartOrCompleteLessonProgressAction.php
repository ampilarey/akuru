<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Progress\Actions\RecordLessonProgressAction;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

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

        // SPEC §27: "Admin must be able to configure completion rules" and
        // "Use a dedicated completion calculation service. Do not put
        // completion rules directly inside controllers."
        //
        // There was no rule and no service: the controller posted `completed`
        // and this stored it. A student could open a lesson holding a required
        // activity, never attempt it, click Mark complete, and the lesson
        // counted — feeding course progress, which feeds the enrolment's
        // percentage, which is what §39's `min_progress_percent` certificate
        // rule is measured against.
        //
        // Only `completed` is gated. Recording `in_progress` on open must stay
        // unconditional, or opening a gated lesson would fail.
        if ($status === 'completed') {
            $completion = app(EvaluateLessonCompletionAction::class)->execute($lesson, (int) $enrollment->id);
            if (! $completion['allowed']) {
                throw ValidationException::withMessages([
                    'lesson' => 'Finish the required activities first: '.implode(', ', $completion['outstanding']).'.',
                ]);
            }
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
