<?php

namespace App\Domains\Progress\Actions;

class GetLatestAssessmentAttemptAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(int $assessmentId, ?int $enrollmentId, bool $includeKeys = false, ?int $studentId = null): ?array
    {
        $attempt = app(StartAssessmentAttemptAction::class)
            ->scopedQuery($assessmentId, $enrollmentId, $studentId)
            ->orderByDesc('attempt_number')
            ->first();

        return $attempt
            ? app(StartAssessmentAttemptAction::class)->serialize($attempt, includeKeys: $includeKeys)
            : null;
    }
}
