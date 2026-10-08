<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryReviewAssignment;
use Illuminate\Validation\ValidationException;

/**
 * RESEARCH_ARTICLES_PLAN R3b: before a reviewer reads a paper they confirm
 * they have no conflict of interest with it — the reviewer's side of the
 * writer's declarations (LIBRARY_PLAN §11.5). The text is withheld until
 * they do (`ListMyReviewAssignmentsAction`), and no report is taken before
 * it (`SubmitResearchReviewAction`). Recorded once, with the time.
 */
class DeclareReviewerNoConflictAction
{
    public function execute(int $userId, int $assignmentId): LibraryReviewAssignment
    {
        $assignment = LibraryReviewAssignment::query()->findOrFail($assignmentId);
        if ((int) $assignment->reviewer_user_id !== $userId) {
            throw ValidationException::withMessages(['assignment' => __('common.review_error_not_yours')]);
        }
        if ($assignment->coi_declared_at === null) {
            $assignment->forceFill(['coi_declared_at' => now()])->save();
        }

        return $assignment->refresh();
    }
}
