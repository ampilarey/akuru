<?php

namespace App\Domains\HR\Actions;

use App\Domains\Academics\Actions\ListCalendarHolidaysAction;
use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use Carbon\Carbon;

/**
 * A leave's days (KNOWN_ISSUES 2026-09-13, decided 2026-10-21, STATUS
 * §5um). Counted in working days by default: a leave across a weekend or a
 * public holiday spends no entitlement on days nobody works. The weekend
 * and the switch are `config/hr.php`; the holidays and closures are the
 * school calendar's.
 */
class CountLeaveDaysAction
{
    public function execute(string $from, string $to, bool $halfDay = false): float
    {
        $days = $this->dates($from, $to);
        if ($halfDay) {
            return $days === [] ? 0.0 : 0.5;
        }

        return (float) count($days);
    }

    /**
     * The dates the leave takes, first to last: every day, or the working
     * days only.
     *
     * @return list<string>
     */
    public function dates(string $from, string $to): array
    {
        $start = Carbon::parse($from, 'Indian/Maldives')->startOfDay();
        $end = Carbon::parse($to, 'Indian/Maldives')->startOfDay();
        if ($end->lt($start)) {
            return [];
        }
        $working = config('hr.leave_counts', 'working') !== 'calendar';
        $weekend = array_map('intval', (array) config('hr.weekend_days', [5, 6]));
        $holidays = $working ? $this->holidays($start, $end) : [];

        $dates = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($working && (in_array($day->dayOfWeek, $weekend, true) || isset($holidays[$day->toDateString()]))) {
                continue;
            }
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    /** @return array<string, true> the calendar's holidays and closures in the years the leave touches */
    private function holidays(Carbon $start, Carbon $end): array
    {
        $years = [];
        foreach ([$start, $end] as $date) {
            $year = app(ResolveAcademicYearForDateAction::class)->execute($date->toDateString());
            if ($year !== null) {
                $years[(int) $year['id']] = true;
            }
        }
        $holidays = [];
        foreach (array_keys($years) as $id) {
            foreach (app(ListCalendarHolidaysAction::class)->execute($id) as $row) {
                if (! empty($row['date'])) {
                    $holidays[(string) $row['date']] = true;
                }
            }
        }

        return $holidays;
    }
}
