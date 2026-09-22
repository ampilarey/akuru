<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Models\AcademicYear;
use Illuminate\Support\Collection;

class ListAcademicYearsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(): Collection
    {
        return AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'start_date', 'end_date', 'status'])
            ->map(fn (AcademicYear $year) => [
                'id' => $year->id,
                'name' => $year->name,
                'start_date' => $year->start_date?->toDateString(),
                'end_date' => $year->end_date?->toDateString(),
                'status' => $year->status?->value ?? $year->status,
                // Kept in the shape for the screens that read it, but derived:
                // `status` is the single answer to "which year is active"
                // (ADR-037), so the legacy column is not consulted.
                'is_current' => $year->status === AcademicYearStatus::Active,
            ]);
    }
}
