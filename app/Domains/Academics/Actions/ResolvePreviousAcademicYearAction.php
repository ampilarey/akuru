<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\AcademicYear;

class ResolvePreviousAcademicYearAction
{
    /**
     * @return array{id: int, name: string, start_date: ?string}|null
     */
    public function execute(int $yearId): ?array
    {
        $year = AcademicYear::query()->find($yearId);
        if ($year === null || $year->start_date === null) {
            return null;
        }

        $previous = AcademicYear::query()
            ->where('start_date', '<', $year->start_date)
            ->orderByDesc('start_date')
            ->first();

        if ($previous === null) {
            return null;
        }

        return [
            'id' => $previous->id,
            'name' => $previous->name,
            'start_date' => $previous->start_date?->toDateString(),
        ];
    }
}
