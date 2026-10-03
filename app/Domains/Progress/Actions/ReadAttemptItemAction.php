<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Models\ActivityAttempt;
use App\Domains\Progress\Models\AssessmentAttempt;

/**
 * Which activity or assessment an attempt was made at, and what it is marked
 * out of — for a caller outside Progress that must not read the attempt models
 * itself (rule 3). Moodle parity slice M2: the rubric is the item's.
 */
class ReadAttemptItemAction
{
    /**
     * @return array{item_id: int, course_id: int, max_score: ?int}|null
     */
    public function execute(string $kind, int $attemptId): ?array
    {
        $attempt = match ($kind) {
            'activity' => ActivityAttempt::query()->find($attemptId, ['id', 'activity_id', 'course_id', 'max_score']),
            'assessment' => AssessmentAttempt::query()->find($attemptId, ['id', 'assessment_id', 'course_id', 'max_score']),
            default => null,
        };
        if ($attempt === null) {
            return null;
        }

        return [
            'item_id' => (int) ($kind === 'activity' ? $attempt->activity_id : $attempt->assessment_id),
            'course_id' => (int) $attempt->course_id,
            'max_score' => $attempt->max_score !== null ? (int) $attempt->max_score : null,
        ];
    }
}
