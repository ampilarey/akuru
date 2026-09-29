<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemReview;
use Illuminate\Validation\ValidationException;

/**
 * L5 (§43.3 — admin must approve content): the editor decides a submitted
 * item. approve → published via the ONE publisher (PublishLibraryItemAction,
 * which stamps approved_by); changes_requested → back to the writer with a
 * comment; rejected → terminal until resubmitted as a new draft. Every
 * decision lands in the append-only review trail.
 */
class ReviewLibraryItemSubmissionAction
{
    public function execute(int $itemId, int $reviewerUserId, string $decision, ?string $comment = null): LibraryItem
    {
        if (! in_array($decision, ['approved', 'changes_requested', 'rejected'], true)) {
            throw ValidationException::withMessages(['decision' => 'Decision must be approved, changes_requested, or rejected.']);
        }

        $item = LibraryItem::query()->findOrFail($itemId);
        $status = $item->status instanceof LibraryItemStatus ? $item->status : LibraryItemStatus::tryFrom((string) $item->status);
        if ($status !== LibraryItemStatus::Submitted) {
            throw ValidationException::withMessages(['item' => 'Only submitted items can be reviewed.']);
        }

        // R3 (D2): research needs its peer-review accepts before approval;
        // the publisher checks again, so no path skips it.
        if ($decision === 'approved') {
            app(AssertResearchReviewedAction::class)->execute($item);
        }

        if ($decision === 'approved') {
            $item = app(PublishLibraryItemAction::class)->execute($item->id, $reviewerUserId);
        } else {
            $item->status = $decision === 'rejected' ? LibraryItemStatus::Rejected : LibraryItemStatus::ChangesRequested;
            $item->save();
            $item = $item->refresh();
        }

        LibraryItemReview::query()->create([
            'library_item_id' => $item->id,
            'reviewer_user_id' => $reviewerUserId,
            'decision' => $decision,
            'comment' => $comment,
        ]);

        // §41: the writer hears the decision, with the reason. "Published"
        // is sent by the one publisher, so an approval is not told twice.
        if ($decision !== 'approved' && $item->writer?->user_id) {
            app(NotifyLibraryUserAction::class)->execute(
                (int) $item->writer->user_id,
                $decision === 'rejected' ? 'Submission not accepted' : 'Changes requested',
                '"'.$item->title.'": '.($comment !== null && trim($comment) !== '' ? trim($comment) : ($decision === 'rejected' ? 'the editor did not accept it.' : 'the editor asked for changes.')),
                '/write',
                'submission_decided',
            );
        }

        return $item;
    }
}
