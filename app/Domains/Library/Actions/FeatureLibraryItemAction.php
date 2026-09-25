<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItem;

/**
 * §7.8 "featuring": the office puts an item at the top of the shelf, or
 * takes it down. Only the flag changes; publication is the publisher's.
 */
class FeatureLibraryItemAction
{
    public function execute(int $itemId, bool $featured): LibraryItem
    {
        $item = LibraryItem::query()->findOrFail($itemId);
        $item->featured = $featured;
        $item->featured_at = $featured ? ($item->featured_at ?? now()) : null;
        $item->save();

        return $item->refresh();
    }
}
