<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\CalendarDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The school calendar as a family or a teacher sees it.
 *
 * Deliberately **not** an extension of ListCalendarHolidaysAction, which looks
 * like the obvious place for this and is the wrong one: HR reads that action to
 * decide which days staff are not expected in
 * (`AutoFillHolidayStaffAttendanceAction`, `RecordStaffAttendanceAction`).
 * Broadening it would have marked every teacher on holiday for a sports day.
 * Two questions that happen to read one table are two actions.
 *
 * "Public" is the explicit `is_public` flag, not a type whitelist — a school
 * meeting and a parents' evening are both `event`, and only one of them is
 * anybody's business outside the office.
 */
class ListPublicCalendarAction
{
    /**
     * @return array{upcoming: list<array<string, mixed>>, past: list<array<string, mixed>>}
     */
    public function execute(?int $yearId = null): array
    {
        $yearId ??= (int) AcademicYear::query()->where('status', 'active')->value('id');

        if ($yearId === 0) {
            return ['upcoming' => [], 'past' => []];
        }

        $today = Carbon::now(config('app.timezone'))->startOfDay();

        $rows = CalendarDay::query()
            ->where('academic_year_id', $yearId)
            ->where('is_public', true)
            ->orderBy('date')
            ->get()
            ->map(fn (CalendarDay $day): array => [
                'id' => (int) $day->id,
                'date' => $day->date?->toDateString(),
                'type' => $day->type->value,
                'title' => $day->title,
                'title_arabic' => $day->title_arabic,
                'title_dhivehi' => $day->title_dhivehi,
                // The single fact a family actually acts on. Said plainly
                // rather than left to be inferred from the type, because
                // "special_schedule" tells a parent nothing about whether to
                // send their child in.
                'no_school' => (bool) $day->affects_timetable,
            ]);

        // `notes` is never returned. It is the office's own working field on a
        // shared row, and publishing the row must not publish the margin.
        $isPast = fn (array $row): bool => $row['date'] !== null
            && Carbon::parse($row['date'], config('app.timezone'))->lt($today);

        return [
            'upcoming' => $rows->reject($isPast)->values()->all(),
            // Newest first: last week matters more than last August.
            'past' => $rows->filter($isPast)->sortByDesc('date')->values()->all(),
        ];
    }

    /**
     * The next few entries, for a home tile.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function upcoming(?int $yearId = null, int $limit = 3): Collection
    {
        return collect($this->execute($yearId)['upcoming'])->take($limit);
    }
}
