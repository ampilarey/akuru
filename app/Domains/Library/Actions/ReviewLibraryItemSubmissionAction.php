<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemReview;
use App\Domains\Library\Models\LibraryReviewAssignment;
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

        // L7 (§12.2/§29): research needs a peer reviewer's accept before the
        // editor can publish, while the requirement is switched on.
        if ($decision === 'approved'
            && $item->content_type === LibraryContentType::Research
            && config('library.research_review_required')
            && ! LibraryReviewAssignment::query()
                ->where('library_item_id', $item->id)
                ->where('status', 'done')
                ->where('recommendation', 'accept')
                ->exists()) {
            throw ValidationException::withMessages([
                'item' => 'Research needs a peer reviewer accept recommendation before publishing.',
            ]);
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

        return $item;
    }
}
