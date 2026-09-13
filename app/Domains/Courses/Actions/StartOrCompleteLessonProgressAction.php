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
            // SPEC §25 lists `course offering ID nullable` on the progress row
            // and says offering progress "should be calculated in the context
            // of that offering". The column existed, the writer accepted it,
            // and this — its only caller — never sent it, so **every** row was
            // null even for a student enrolled through an offering. A progress
            // record that cannot say which batch it belongs to is not a record
            // of that batch.
            'course_offering_id' => $enrollment->course_offering_id,
            'course_module_id' => $lesson->course_module_id,
            'lesson_id' => $lesson->id,
            'lesson_revision_id' => $lesson->current_revision_id,
            'student_id' => $enrollment->unified_student_id,
            'status' => $status,
            // §25's `score_summary`, written for the first time. Only on
            // completion: "how did they do" is a question about a finished
            // lesson, and rewriting it on every page open would churn the row
            // and lose the figure the completion was actually judged on.
            'score_summary' => $status === 'completed'
                ? app(SummarizeLessonScoreAction::class)->execute($lesson, (int) $enrollment->id)
                : null,
        ]);

        app(SyncEnrollmentProgressAction::class)->execute($enrollment->fresh());

        return $recorded + ['recorded' => true];
    }
}
