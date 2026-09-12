<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Enums\AssessmentAttemptStatus;
use Illuminate\Validation\ValidationException;

class SaveAssessmentAttemptAction
{
    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function execute(int $assessmentId, ?int $enrollmentId, array $answers, ?int $studentId = null): array
    {
        $attempt = app(StartAssessmentAttemptAction::class)
            ->scopedQuery($assessmentId, $enrollmentId, $studentId)
            ->where('status', AssessmentAttemptStatus::InProgress)
            ->orderByDesc('attempt_number')
            ->first();

        if ($attempt === null) {
            throw ValidationException::withMessages([
                'attempt' => ['Start the assessment before saving answers.'],
            ]);
        }

        // SPEC §31: autosave is the loophole that would make the submit-side
        // cut-off pointless. A browser left open past the deadline keeps
        // posting answers, and without this check every one of them would be
        // accepted and then scored as "what was in hand when time ran out".
        $settings = app(\App\Domains\Courses\Actions\ResolveAssessmentSettingsAction::class)
            ->execute($assessmentId);
        $deadline = app(ResolveAssessmentDeadlineAction::class)->execute($attempt, $settings);

        if ($deadline['expired']) {
            throw ValidationException::withMessages([
                'attempt' => ['Time is up for this assessment. Your saved answers have been kept.'],
            ]);
        }

        $attempt->update([
            'answers' => $answers,
            'last_saved_at' => now(),
        ]);

        return app(StartAssessmentAttemptAction::class)->serialize($attempt->fresh());
    }
}
