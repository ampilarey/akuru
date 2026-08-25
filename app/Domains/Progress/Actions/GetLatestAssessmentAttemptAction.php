<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Models\AssessmentAttempt;

class GetLatestAssessmentAttemptAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(int $assessmentId, int $enrollmentId, bool $includeKeys = false): ?array
    {
        $attempt = AssessmentAttempt::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('assessment_id', $assessmentId)
            ->orderByDesc('attempt_number')
            ->first();

        return $attempt
            ? app(StartAssessmentAttemptAction::class)->serialize($attempt, includeKeys: $includeKeys)
            : null;
    }
}
