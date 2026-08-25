<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\PayrollPeriod;
use Carbon\Carbon;

class IsPayrollMonthLockedAction
{
    public function execute(string $date): bool
    {
        $parsed = Carbon::parse($date, 'Indian/Maldives');

        $period = PayrollPeriod::query()
            ->where('year', $parsed->year)
            ->where('month', $parsed->month)
            ->first();

        return $period?->isLockedForEdits() ?? false;
    }
}
