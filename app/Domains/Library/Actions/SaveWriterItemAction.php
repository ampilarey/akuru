<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * L5 (§7.4): a writer edits ONLY their own items, and only while the item
 * is draft or changes_requested — once submitted or published the text is
 * out of their hands (§43.1: writers never publish directly; §43.3: admin
 * owns the final state). Price is the writer's SUGGESTION — admin can
 * change it before publishing. Reuses the one item writer (SaveLibraryItemAction)
 * so pages, tags, authors, and the private PDF behave identically.
 */
class SaveWriterItemAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $userId, array $data, ?int $itemId = null, ?UploadedFile $pdf = null): LibraryItem
    {
        $profile = WriterProfile::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();
        if ($profile === null) {
            throw ValidationException::withMessages(['writer' => 'An approved writer profile is required.']);
        }

        $item = null;
        if ($itemId !== null) {
            $item = LibraryItem::query()->findOrFail($itemId);
            if ((int) $item->writer_id !== (int) $profile->id) {
                throw ValidationException::withMessages(['item' => 'You can only edit your own items.']);
            }
            $status = $item->status instanceof LibraryItemStatus ? $item->status : LibraryItemStatus::tryFrom((string) $item->status);
            if (! in_array($status, [LibraryItemStatus::Draft, LibraryItemStatus::ChangesRequested], true)) {
                throw ValidationException::withMessages(['item' => 'Only drafts and change-requested items can be edited.']);
            }
        }

        return app(SaveLibraryItemAction::class)->execute(
            $data + ['created_by' => $userId, 'writer_id' => $profile->id],
            $item,
            $pdf,
        );
    }
}
