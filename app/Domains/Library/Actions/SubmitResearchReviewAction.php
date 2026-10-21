<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemReview;
use App\Domains\Library\Models\LibraryReviewAssignment;
use App\Support\Said;
use Illuminate\Validation\ValidationException;

/**
 * L7 (§12.2): the assigned reviewer recommends accept / revise / reject
 * with comments. The comment lands in the SAME append-only editorial
 * trail the writer already reads (§43.8 — the writer sees editorial
 * feedback, never who else is reviewing).
 *
 * R3 (RESEARCH_ARTICLES_PLAN): a report belongs to the item's current
 * review round, and only while the item is with the reviewers.
 *
 * - **revise** sends the item back to the writer as `changes_requested`;
 *   their resubmission opens the next round (`SubmitLibraryItemForReviewAction`).
 * - Every recommendation reaches the writer with the comment — never the
 *   reviewer's name (§43.8, D6).
 * - The office hears when the required accepts are reached ("ready to
 *   publish"), and when a reviewer recommends rejecting.
 */
class SubmitResearchReviewAction
{
    public function execute(int $userId, int $assignmentId, string $recommendation, ?string $comment = null): LibraryReviewAssignment
    {
        if (! in_array($recommendation, ['accept', 'revise', 'reject'], true)) {
            throw ValidationException::withMessages(['recommendation' => __('common.review_error_recommendation')]);
        }

        $assignment = LibraryReviewAssignment::query()->findOrFail($assignmentId);
        if ((int) $assignment->reviewer_user_id !== $userId) {
            throw ValidationException::withMessages(['assignment' => __('common.review_error_not_yours')]);
        }

        // R3b: no report without the conflict-of-interest declaration.
        if ($assignment->coi_declared_at === null) {
            throw ValidationException::withMessages(['assignment' => __('common.review_error_coi')]);
        }

        $item = LibraryItem::query()->findOrFail($assignment->library_item_id);
        $status = $item->status instanceof LibraryItemStatus ? $item->status : LibraryItemStatus::tryFrom((string) $item->status);
        if ($status !== LibraryItemStatus::Submitted) {
            throw ValidationException::withMessages(['assignment' => __('common.review_error_not_with_reviewers')]);
        }
        if ((int) $assignment->round !== max(1, (int) $item->review_round)) {
            throw ValidationException::withMessages(['assignment' => __('common.review_error_round_closed')]);
        }

        $assignment->fill(['status' => 'done', 'recommendation' => $recommendation])->save();

        LibraryItemReview::query()->create([
            'library_item_id' => $assignment->library_item_id,
            'reviewer_user_id' => $userId,
            'decision' => 'reviewer_'.$recommendation,
            'comment' => $comment,
        ]);

        $notify = app(NotifyLibraryUserAction::class);
        $said = $comment !== null && trim($comment) !== '' ? ' "'.trim($comment).'"' : '';

        // A revision goes back to the writer now; the round closes.
        if ($recommendation === 'revise') {
            $item->status = LibraryItemStatus::ChangesRequested;
            $item->save();
        }

        if ($item->writer?->user_id) {
            $notify->execute(
                (int) $item->writer->user_id,
                Said::of('library_notices.'.match ($recommendation) {
                    'accept' => 'research_accepted_title',
                    'revise' => 'research_revise_title',
                    default => 'research_rejected_title',
                }),
                Said::join([
                    Said::of('library_notices.about_item', ['title' => $item->title, 'comment' => $said !== '' ? trim($said) : Said::of('library_notices.research_no_comment')]),
                    $recommendation === 'revise' ? Said::of('library_notices.research_revise_again') : null,
                ]),
                '/write',
            );
        }

        $gate = app(AssertResearchReviewedAction::class);
        if ($recommendation === 'accept' && $gate->acceptsInRound($item) >= $gate->required()) {
            $notify->office(Said::of('library_notices.research_ready_title'), Said::of('library_notices.research_ready_body', ['title' => $item->title]), '/admin/library');
        }
        if ($recommendation === 'reject') {
            $notify->office(Said::of('library_notices.research_reject_title'), Said::of('library_notices.research_reject_body', ['title' => $item->title]), '/admin/library');
        }

        return $assignment->refresh();
    }
}
