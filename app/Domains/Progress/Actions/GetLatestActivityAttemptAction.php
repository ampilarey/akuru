<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Models\ActivityAttempt;

class GetLatestActivityAttemptAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(int $activityId, int $enrollmentId): ?array
    {
        $attempt = ActivityAttempt::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->orderByDesc('attempt_number')
            ->first();

        return $attempt ? app(SaveActivityAttemptAction::class)->serialize($attempt) : null;
    }
}
