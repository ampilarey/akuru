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
                'start_time' => substr((string) $period->start_time, 0, 5),
                'end_time' => substr((string) $period->end_time, 0, 5),
            ])
            ->all();
    }
}
