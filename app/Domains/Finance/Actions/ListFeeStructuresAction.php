<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\FeeStructure;
use Illuminate\Support\Collection;

class ListFeeStructuresAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $yearId = null): Collection
    {
        $query = FeeStructure::query()->with('items')->orderBy('name');
        if ($yearId) {
            $query->where('academic_year_id', $yearId);
        }

        return $query->get()->map(fn (FeeStructure $structure) => [
            'id' => $structure->id,
            'academic_year_id' => $structure->academic_year_id,
            'name' => $structure->name,
            'applies_to' => $structure->applies_to?->value,
            'class_ids' => $structure->class_ids ?? [],
            'status' => $structure->status?->value,
            'items' => $structure->items->map(fn ($item) => [
                'id' => $item->id,
                'fee_item_id' => $item->fee_item_id,
                'amount' => $item->amount,
                'frequency' => $item->frequency?->value,
                'due_day' => $item->due_day,
                'is_mandatory' => $item->is_mandatory,
            ])->values(),
        ]);
    }
}
