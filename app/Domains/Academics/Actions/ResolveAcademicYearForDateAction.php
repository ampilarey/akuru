<?php

namespace App\Domains\Academics\Actions;

class ResolveAcademicYearForDateAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(?string $date = null): ?array
    {
        $date ??= now('Indian/Maldives')->toDateString();
        $years = app(ListAcademicYearsAction::class)->execute();

        $match = $years->first(
            fn (array $year): bool => ($year['start_date'] ?? '') <= $date && ($year['end_date'] ?? '') >= $date
        );

        if ($match !== null) {
            return $match;
        }

        return $years->firstWhere('is_current', true)
            ?? $years->firstWhere('status', 'active');
    }
}
