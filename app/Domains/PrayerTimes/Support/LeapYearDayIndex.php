<?php

namespace App\Domains\PrayerTimes\Support;

use Carbon\Carbon;

final class LeapYearDayIndex
{
    /**
     * Bake&Grill leap-year index: source tables are 366-row leap calendars.
     * Non-leap years add 1 from day 60 (1 March) onward.
     */
    public static function for(Carbon $date): int
    {
        $dayOfYear = (int) $date->dayOfYear;

        if (! $date->isLeapYear() && $dayOfYear >= 60) {
            return $dayOfYear + 1;
        }

        return $dayOfYear;
    }
}
