<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItemReview;
use App\Domains\Library\Models\LibraryReviewAssignment;
use Illuminate\Validation\ValidationException;

/**
 * L7 (§12.2): the assigned reviewer recommends accept / revise / reject
 * with comments. The comment lands in the SAME append-only editorial
 * trail the writer already reads (§43.8 — the writer sees editorial
 * feedback, never who else is reviewing). Re-reviews update the
 * recommendation and append a new trail row.
 */
class SubmitResearchReviewAction
{
    public function execute(int $userId, int $assignmentId, string $recommendation, ?string $comment = null): LibraryReviewAssignment
    {
        if (! in_array($recommendation, ['accept', 'revise', 'reject'], true)) {
            throw ValidationException::withMessages(['recommendation' => 'Recommendation must be accept, revise, or reject.']);
        }

        $assignment = LibraryReviewAssignment::query()->findOrFail($assignmentId);
        if ((int) $assignment->reviewer_user_id !== $userId) {
            throw ValidationException::withMessages(['assignment' => 'This review is not assigned to you.']);
        }

        $assignment->fill(['status' => 'done', 'recommendation' => $recommendation])->save();

        LibraryItemReview::query()->create([
            'library_item_id' => $assignment->library_item_id,
            'reviewer_user_id' => $userId,
            'decision' => 'reviewer_'.$recommendation,
            'comment' => $comment,
        ]);

        return $assignment->refresh();
    }
}
