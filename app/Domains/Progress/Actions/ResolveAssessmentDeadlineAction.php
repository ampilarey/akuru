<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Models\AssessmentAttempt;
use Carbon\CarbonInterface;

/**
 * SPEC §31: "Assessment countdowns and time limits must be computed
 * **server-side** from `attempt.started_at` and configured time limit. Never
 * trust client device clocks for time-limit enforcement."
 *
 * `assessments.time_limit_minutes` was settable in the admin screen, stored,
 * and listed to the student — and enforced **nowhere**. Not on submit, and not
 * even by a client-side countdown, so the field was decorative: a teacher set
 * thirty minutes and a student could take a week.
 *
 * One place computes the deadline, so the countdown the student sees and the
 * cut-off the server applies can never disagree — which is the failure mode
 * §31's second sentence is really about.
 */
class ResolveAssessmentDeadlineAction
{
    /**
     * Submissions are accepted this long after the deadline.
     *
     * Not generosity: a student pressing Submit on the last second still has to
     * get the request across a Maldivian mobile connection, and refusing that
     * would fail exactly the person who obeyed the limit. Short enough that it
     * cannot be used as extra time.
     */
    public const GRACE_SECONDS = 30;

    /**
     * @param  array<string, mixed>  $settings  from `ResolveAssessmentSettingsAction`
     * @return array{
     *     limited: bool,
     *     deadline: CarbonInterface|null,
     *     seconds_remaining: int|null,
     *     expired: bool,
     *     seconds_over: int
     * }
     */
    public function execute(AssessmentAttempt $attempt, array $settings): array
    {
        $minutes = $settings['time_limit_minutes'] ?? null;

        if ($minutes === null || (int) $minutes <= 0 || $attempt->started_at === null) {
            return [
                'limited' => false,
                'deadline' => null,
                'seconds_remaining' => null,
                'expired' => false,
                'seconds_over' => 0,
            ];
        }

        $deadline = $attempt->started_at->copy()->addMinutes((int) $minutes);
        $now = now();

        // Signed difference, then clamped once. Positive while the deadline is
        // still ahead, negative once it has passed.
        $remaining = $now->diffInSeconds($deadline, false);

        return [
            'limited' => true,
            'deadline' => $deadline,
            // Never negative: a countdown that goes below zero reads as a bug
            // to the person watching it.
            'seconds_remaining' => (int) max(0, $remaining),
            'expired' => $remaining < -self::GRACE_SECONDS,
            'seconds_over' => (int) max(0, -$remaining),
        ];
    }
}
