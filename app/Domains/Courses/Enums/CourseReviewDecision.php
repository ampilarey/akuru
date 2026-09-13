<?php

namespace App\Domains\Courses\Enums;

/**
 * SPEC §35 lists three outcomes a dean or supervisor may reach:
 *
 *   > Approve courses · Reject courses · Request changes
 *
 * Two of them — reject and request changes — land the course back in `draft`,
 * because that is the only way back the workflow has and inventing a second
 * one would fork the status column for a difference that is not about state.
 * The difference between them is **what the creator is told**, which is why it
 * lives in the decision record rather than in `workflow_status`.
 *
 * Kept deliberately narrow: a course that is genuinely finished with is
 * archived, which is an existing transition and a different act from refusing
 * a draft.
 */
enum CourseReviewDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ChangesRequested = 'changes_requested';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::ChangesRequested => 'Changes requested',
        };
    }

    public function targetStatus(): CourseWorkflowStatus
    {
        return match ($this) {
            self::Approved => CourseWorkflowStatus::Published,
            self::Rejected, self::ChangesRequested => CourseWorkflowStatus::Draft,
        };
    }

    /**
     * Approving is the one decision that says nothing is wrong, so it is the
     * one that may be silent. A refusal without a reason is the defect this
     * whole slice exists to fix, and re-creating it behind a nullable column
     * would be a poor joke.
     */
    public function requiresComment(): bool
    {
        return $this !== self::Approved;
    }
}
