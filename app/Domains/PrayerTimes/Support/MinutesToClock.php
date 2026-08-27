<?php

namespace App\Domains\PrayerTimes\Support;

final class MinutesToClock
{
    public static function format(int $minutes): string
    {
        $wrapped = $minutes % 1440;
        if ($wrapped < 0) {
            $wrapped += 1440;
        }

        $hours = intdiv($wrapped, 60);
        $mins = $wrapped % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }
}
