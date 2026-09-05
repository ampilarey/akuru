<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\CalendarDay;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\Timetable;
use Carbon\Carbon;

/**
 * The next date a class actually meets a subject, after a given date.
 *
 * Homework due "tomorrow" is wrong when the class does not meet tomorrow — the
 * pupil hands nothing in and the teacher wonders why. EduPage's due-date picker
 * highlights the days a class actually meets; this is the same idea reduced to
 * the one answer the register needs.
 *
 * Returns null when the class has no further lesson inside the search window,
 * which the caller should treat as "no sensible default", not as an error.
 */
class ResolveNextLessonDateForClassAction
{
    private const SEARCH_DAYS = 21;

    public function execute(int $classId, ?int $subjectId, string $afterDate): ?string
    {
        $class = ClassRoom::query()->find($classId);
        if ($class === null) {
            return null;
        }

        $entries = Timetable::query()
            ->where('academic_year_id', $class->academic_year_id)
            ->where('class_id', $classId)
            ->where('is_active', true)
            ->whereNotNull('period_id')
            ->when($subjectId !== null, fn ($query) => $query->where('subject_id', $subjectId))
            ->get();

        if ($entries->isEmpty()) {
            return null;
        }

        // The same blocking rule GenerateExpectedRegistersAction uses, so a due
        // date is never set on a day the registers will never create.
        $blocked = CalendarDay::query()
            ->where('academic_year_id', $class->academic_year_id)
            ->where('affects_timetable', true)
            ->pluck('date')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->flip();

        $cursor = Carbon::parse($afterDate, config('app.timezone'))->startOfDay();

        for ($offset = 1; $offset <= self::SEARCH_DAYS; $offset++) {
            $day = $cursor->copy()->addDays($offset);
            $dateStr = $day->toDateString();

            if ($blocked->has($dateStr)) {
                continue;
            }

            $meets = $entries->contains(function (Timetable $entry) use ($day): bool {
                if (strtolower($entry->day_of_week) !== strtolower($day->englishDayOfWeek)) {
                    return false;
                }

                return ! (
                    ($entry->valid_from && $day->lt($entry->valid_from))
                    || ($entry->valid_until && $day->gt($entry->valid_until))
                );
            });

            if ($meets) {
                return $dateStr;
            }
        }

        return null;
    }
}
