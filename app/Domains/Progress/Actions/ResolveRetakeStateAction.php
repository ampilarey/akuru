<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Enums\ActivityAttemptStatus;
use App\Domains\Progress\Enums\AssessmentAttemptStatus;
use App\Domains\Progress\Models\ActivityAttempt;

/**
 * How many goes has this learner had, and may they have another?
 *
 * ## The gap this closes
 *
 * The retake machinery was complete except for the one person it is about.
 * Authors set `retakes_allowed` and `retake_limit` — the authoring forms
 * default to 3 for an activity and 2 for an assessment. Both submit paths
 * enforce the policy. `nextNumber()` exists on both to number attempt two, and
 * `SubmitActivityAttemptAction` creates it. The teacher's revision report says
 * in so many words: *"Retry the weak item when retakes remain; otherwise review
 * with a teacher."*
 *
 * **And no learner could ever start a second attempt.** Both players compute
 * `submitted = attempt && attempt.status !== 'in_progress'` and disable every
 * input and every button on it, permanently, with no control to begin again.
 * So a pupil told by their teacher to try again had nothing to press, and a
 * policy three screens deep in the authoring UI could not be exercised by
 * anybody.
 *
 * The same shape as the status columns in STATUS §5dq: a setting that is
 * configured, enforced and reported on, whose non-default behaviour no code
 * path can reach. Found by running a smoke walk twice (STATUS §5ed) — the
 * second run could not answer the question the first one had.
 *
 * ## Why the rule lives here
 *
 * It was in two places and is now in one (rule 11). The two guards had drifted
 * apart already: an activity honours `retakes_allowed` **and** `retake_limit`,
 * an assessment only ever read `retake_limit`. Both still call this, so the
 * player's "can I try again?" and the server's "may you?" cannot disagree —
 * which matters more than usual here, because a button offered and then refused
 * is worse than no button.
 *
 * The difference between the two policies is preserved rather than quietly
 * unified: widening assessments to honour `retakes_allowed` would change
 * behaviour for existing assessments, and that is a decision, not a tidy-up.
 */
class ResolveRetakeStateAction
{
    /**
     * @param  array<string, mixed>  $settings
     * @return array{used: int, limit: int|null, allowed: bool, remaining: int|null, can_retake: bool}
     */
    public function forActivity(int $activityId, int $enrollmentId, array $settings): array
    {
        $used = ActivityAttempt::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->whereIn('status', [ActivityAttemptStatus::Submitted, ActivityAttemptStatus::Scored])
            ->count();

        return $this->state(
            used: $used,
            allowed: (bool) ($settings['retakes_allowed'] ?? true),
            limit: isset($settings['retake_limit']) && $settings['retake_limit'] !== ''
                ? (int) $settings['retake_limit']
                : null,
        );
    }

    /**
     * @return array{used: int, limit: int|null, allowed: bool, remaining: int|null, can_retake: bool}
     */
    public function forAssessment(int $assessmentId, ?int $enrollmentId, ?int $studentId, mixed $retakeLimit): array
    {
        $used = app(StartAssessmentAttemptAction::class)
            ->scopedQuery($assessmentId, $enrollmentId, $studentId)
            ->whereIn('status', [AssessmentAttemptStatus::Submitted, AssessmentAttemptStatus::Scored])
            ->count();

        return $this->state(
            used: $used,
            // Assessments have never had a `retakes_allowed` switch — only a
            // limit. Left as it is on purpose: see the class docblock.
            allowed: true,
            limit: $retakeLimit !== null && $retakeLimit !== '' ? (int) $retakeLimit : null,
        );
    }

    /**
     * @return array{used: int, limit: int|null, allowed: bool, remaining: int|null, can_retake: bool}
     */
    private function state(int $used, bool $allowed, ?int $limit): array
    {
        // `remaining` is null for "no limit set", which is not the same as zero
        // and must not render as it. A screen that says "0 left" when the
        // answer is "as many as you like" is worse than saying nothing.
        $remaining = $limit === null ? null : max(0, $limit - $used);

        $canRetake = $used > 0
            && ($allowed || $used < 1)
            && ($remaining === null || $remaining > 0);

        return [
            'used' => $used,
            'limit' => $limit,
            'allowed' => $allowed,
            'remaining' => $remaining,
            'can_retake' => $canRetake,
        ];
    }
}
