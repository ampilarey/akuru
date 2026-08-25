<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\LeaveType;
use Illuminate\Support\Collection;

class ListLeaveTypesAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(bool $activeOnly = false): Collection
    {
        return LeaveType::query()
            ->when($activeOnly, fn ($query) => $query->where('active', true))
            ->orderBy('name')
            ->get()
            ->map(fn (LeaveType $type) => [
                'id' => $type->id,
                'code' => $type->code?->value ?? $type->code,
                'name' => $type->name,
                'name_arabic' => $type->name_arabic,
                'name_dhivehi' => $type->name_dhivehi,
                'days_per_year' => (float) $type->days_per_year,
                'carry_over_max' => (float) $type->carry_over_max,
                'requires_document' => $type->requires_document,
                'paid' => $type->paid,
                'active' => $type->active,
            ])
            ->values();
    }
}
