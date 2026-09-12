<?php

namespace App\Domains\Progress\Actions;

class GetLatestAssessmentAttemptAction
{
    /**
     * @return array<string, mixed>|null
     */
    /**
     * @param  bool  $asStudent  SPEC §19 `show_results` — see `StartAssessmentAttemptAction::serialize()`.
     */
    public function execute(int $assessmentId, ?int $enrollmentId, bool $includeKeys = false, ?int $studentId = null, bool $asStudent = false): ?array
    {
        $attempt = app(StartAssessmentAttemptAction::class)
            ->scopedQuery($assessmentId, $enrollmentId, $studentId)
            ->orderByDesc('attempt_number')
            ->first();

        return $attempt
            ? app(StartAssessmentAttemptAction::class)->serialize($attempt, includeKeys: $includeKeys, asStudent: $asStudent)
            : null;
    }
}
