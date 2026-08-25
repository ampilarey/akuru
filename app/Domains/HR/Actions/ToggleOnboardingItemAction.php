<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\StaffOnboardingItem;

class ToggleOnboardingItemAction
{
    public function execute(int $itemId, bool $done, ?int $doneBy = null): StaffOnboardingItem
    {
        $item = StaffOnboardingItem::query()->findOrFail($itemId);
        $item->done = $done;
        $item->done_by = $done ? $doneBy : null;
        $item->done_at = $done ? now() : null;
        $item->save();

        return $item->refresh();
    }
}
