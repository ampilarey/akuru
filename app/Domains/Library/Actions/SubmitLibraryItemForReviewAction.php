<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemReview;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Validation\ValidationException;

/**
 * L5: a writer hands their own draft (or change-requested revision) to the
 * editorial queue. Logged in the append-only review trail.
 */
class SubmitLibraryItemForReviewAction
{
    public function execute(int $userId, int $itemId): LibraryItem
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->where('status', 'active')->first();
        if ($profile === null) {
            throw ValidationException::withMessages(['writer' => 'An approved writer profile is required.']);
        }

        $item = LibraryItem::query()->findOrFail($itemId);
        if ((int) $item->writer_id !== (int) $profile->id) {
            throw ValidationException::withMessages(['item' => 'You can only submit your own items.']);
        }

        $status = $item->status instanceof LibraryItemStatus ? $item->status : LibraryItemStatus::tryFrom((string) $item->status);
        if (! in_array($status, [LibraryItemStatus::Draft, LibraryItemStatus::ChangesRequested], true)) {
            throw ValidationException::withMessages(['item' => 'Only drafts and change-requested items can be submitted.']);
        }

        $item->status = LibraryItemStatus::Submitted;
        $item->submitted_at = now();
        $item->save();

        LibraryItemReview::query()->create([
            'library_item_id' => $item->id,
            'reviewer_user_id' => null,
            'decision' => 'submitted',
            'comment' => null,
        ]);

        return $item->refresh();
    }
}
