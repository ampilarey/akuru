<?php

namespace App\Domains\HR\Actions;

use Carbon\Carbon;

class CountLeaveDaysAction
{
    public function execute(string $from, string $to, bool $halfDay = false): float
    {
        if ($halfDay) {
            return 0.5;
        }

        $start = Carbon::parse($from, 'Indian/Maldives')->startOfDay();
        $end = Carbon::parse($to, 'Indian/Maldives')->startOfDay();

        if ($end->lt($start)) {
            return 0.0;
        }

        return (float) ($start->diffInDays($end) + 1);
    }
}
