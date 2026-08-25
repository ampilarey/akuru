<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\FeeItem;
use Illuminate\Support\Collection;

class ListFeeItemsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(): Collection
    {
        return FeeItem::query()->orderBy('name')->get()->map(fn (FeeItem $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'name_arabic' => $item->name_arabic,
            'name_dhivehi' => $item->name_dhivehi,
            'description' => $item->description,
            'default_amount' => $item->default_amount,
            'currency' => $item->currency,
            'type' => $item->type?->value,
            'frequency' => $item->frequency?->value,
            'is_mandatory' => $item->is_mandatory,
            'is_active' => $item->is_active,
            'applicable_grades' => $item->applicable_grades,
        ]);
    }
}
