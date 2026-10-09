<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\Period;

/**
 * The teaching periods a form may name — active, in order, breaks left out.
 * Used where somebody outside the timetable picks a period: a guardian's
 * absence note for one lesson rather than the whole day (S2.4).
 */
class ListPeriodOptionsAction
{
    /**
     * @return list<array{id: int, name: string, start_time: string, end_time: string}>
     */
    public function execute(): array
    {
        return Period::query()
            ->where('is_active', true)
            ->where('is_break', false)
            ->orderBy('order')
            ->get(['id', 'name', 'start_time', 'end_time'])
            ->map(fn (Period $period): array => [
                'id' => (int) $period->id,
                'name' => (string) $period->name,
                // The times are cast to dates, and a date as a string starts
                // with its year: the family's form read "Period 1
                // (2026-–2026-)" (found on the OA1 walk, STATUS §5qd).
                'start_time' => $period->start_time?->format('H:i') ?? '',
                'end_time' => $period->end_time?->format('H:i') ?? '',
            ])
            ->all();
    }
}
