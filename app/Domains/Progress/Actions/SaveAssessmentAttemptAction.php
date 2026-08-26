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

        $attempt->update([
            'answers' => $answers,
            'last_saved_at' => now(),
        ]);

        return app(StartAssessmentAttemptAction::class)->serialize($attempt->fresh());
    }
}
