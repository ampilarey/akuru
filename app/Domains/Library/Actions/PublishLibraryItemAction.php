<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;

/**
 * Business rule §43.3: admin must approve content — publishing stamps
 * approved_by. Unpublish returns to draft without clearing history.
 */
class PublishLibraryItemAction
{
    public function execute(int $itemId, int $approvedBy, bool $publish = true): LibraryItem
    {
        $item = LibraryItem::query()->findOrFail($itemId);
        if ($publish) {
            $item->fill([
                'status' => LibraryItemStatus::Published,
                'published_at' => $item->published_at ?? now(),
                'approved_by' => $approvedBy,
            ]);
        } else {
            $item->status = LibraryItemStatus::Draft;
        }
        $item->save();

        return $item->refresh();
    }
}
