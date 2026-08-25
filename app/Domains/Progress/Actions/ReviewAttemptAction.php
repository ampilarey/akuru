<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Enums\ActivityAttemptStatus;
use App\Domains\Progress\Enums\AssessmentAttemptStatus;
use App\Domains\Progress\Models\ActivityAttempt;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Validation\ValidationException;

class ReviewAttemptAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(string $kind, int $attemptId, array $data, int $reviewerId): array
    {
        $score = max(0, (int) ($data['score'] ?? 0));
        $feedback = trim((string) ($data['feedback'] ?? ''));
        $now = now();

        if ($kind === 'activity') {
            $attempt = ActivityAttempt::query()->find($attemptId);
            if ($attempt === null) {
                throw ValidationException::withMessages(['attempt' => ['Activity attempt not found.']]);
            }
            $max = max(1, (int) ($attempt->max_score ?: ($data['max_score'] ?? 1)));
            $attempt->update([
                'status' => ActivityAttemptStatus::Scored,
                'score' => min($score, $max),
                'max_score' => $max,
                'feedback' => $feedback !== '' ? $feedback : null,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => $now,
            ]);

            return app(SaveActivityAttemptAction::class)->serialize($attempt->fresh()) + ['kind' => 'activity'];
        }

        if ($kind === 'assessment') {
            $attempt = AssessmentAttempt::query()->find($attemptId);
            if ($attempt === null) {
                throw ValidationException::withMessages(['attempt' => ['Assessment attempt not found.']]);
            }
            $max = max(1, (int) ($attempt->max_score ?: ($data['max_score'] ?? 1)));
            $attempt->update([
                'status' => AssessmentAttemptStatus::Scored,
                'score' => min($score, $max),
                'max_score' => $max,
                'item_scores' => is_array($data['item_scores'] ?? null) ? $data['item_scores'] : $attempt->item_scores,
                'feedback' => $feedback !== '' ? $feedback : null,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => $now,
            ]);

            return app(StartAssessmentAttemptAction::class)->serialize($attempt->fresh(), includeKeys: true) + ['kind' => 'assessment'];
        }

        throw ValidationException::withMessages(['kind' => ['Review kind must be activity or assessment.']]);
    }
}
