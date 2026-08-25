<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\CalendarDayType;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\CalendarDay;
use Illuminate\Support\Collection;

class ListCalendarHolidaysAction
{
    /**
     * Public/portal holiday read. Closures are included because they cancel school.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $yearId = null): Collection
    {
        $yearId ??= (int) AcademicYear::query()->where('status', 'active')->value('id');

        if ($yearId === 0) {
            return collect();
        }

        return CalendarDay::query()
            ->where('academic_year_id', $yearId)
            ->whereIn('type', [CalendarDayType::Holiday, CalendarDayType::Closure])
            ->orderBy('date')
            ->get()
            ->map(fn (CalendarDay $day) => [
                'id' => $day->id,
                'date' => $day->date?->toDateString(),
                'type' => $day->type->value,
                'title' => $day->title,
                'title_arabic' => $day->title_arabic,
                'title_dhivehi' => $day->title_dhivehi,
                'affects_timetable' => $day->affects_timetable,
            ])
            ->values();
    }
}
