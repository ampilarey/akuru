<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Courses\Actions\ResolveAssessmentSettingsAction;
use App\Domains\Courses\Actions\ScoreAssessmentSnapshotsAction;
use App\Domains\Progress\Enums\AssessmentAttemptStatus;
use Illuminate\Validation\ValidationException;

class SubmitAssessmentAttemptAction
{
    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function execute(int $assessmentId, ?int $enrollmentId, array $answers, ?int $studentId = null): array
    {
        $settings = app(ResolveAssessmentSettingsAction::class)->execute($assessmentId);
        app(StartAssessmentAttemptAction::class)->assertRetakesAvailable(
            $assessmentId,
            $enrollmentId,
            $settings['retake_limit'] ?? null,
            $studentId,
        );

        $attempt = app(StartAssessmentAttemptAction::class)
            ->scopedQuery($assessmentId, $enrollmentId, $studentId)
            ->where('status', AssessmentAttemptStatus::InProgress)
            ->orderByDesc('attempt_number')
            ->first();

        if ($attempt === null) {
            throw ValidationException::withMessages([
                'attempt' => ['Start the assessment before submitting.'],
            ]);
        }

        $result = app(ScoreAssessmentSnapshotsAction::class)->execute(
            $attempt->snapshots ?? [],
            $answers,
            $settings['passing_score'] ?? null,
        );

        $attempt->update([
            'answers' => $answers,
            'status' => AssessmentAttemptStatus::from($result['status']),
            'score' => $result['score'],
            'max_score' => $result['max_score'],
            'submitted_at' => now(),
            'last_saved_at' => now(),
        ]);

        $showKeys = (bool) $settings['show_correct_answers'] && $result['status'] === 'scored';

        return [
            'attempt' => app(StartAssessmentAttemptAction::class)->serialize($attempt->fresh(), includeKeys: $showKeys),
            'result' => $result,
        ];
    }
}
