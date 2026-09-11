<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\AbsenceType;
use Illuminate\Support\Collection;

/**
 * The absence reasons a school offers.
 *
 * Two audiences, one query: a family may only choose an **active** type, while
 * the office sees the inactive ones too, because a type that has been retired
 * is still the reason attached to last term's notes.
 */
class ListAbsenceTypesAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(bool $selectableOnly = true): Collection
    {
        return AbsenceType::query()
            ->when($selectableOnly, fn ($query) => $query->selectable())
            ->when(! $selectableOnly, fn ($query) => $query->orderBy('sort_order')->orderBy('name'))
            ->get()
            ->map(fn (AbsenceType $type): array => [
                'id' => (int) $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'name_dhivehi' => $type->name_dhivehi,
                'name_arabic' => $type->name_arabic,
                'excuses_absence' => $type->excuses_absence,
                'requires_evidence' => $type->requires_evidence,
                'is_active' => $type->is_active,
                'sort_order' => (int) $type->sort_order,
            ])
            ->values();
    }
}
