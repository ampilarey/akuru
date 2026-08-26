<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\GlossaryItem;
use Illuminate\Support\Collection;

class ListGlossaryItemsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(): Collection
    {
        return GlossaryItem::query()
            ->orderBy('term')
            ->get()
            ->map(fn (GlossaryItem $item) => $item->toPayload())
            ->values();
    }
}
