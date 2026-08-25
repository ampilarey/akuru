<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\StaffAttendanceStatus;
use App\Domains\HR\Models\StaffAttendance;
use Carbon\Carbon;

class CountUnpaidLeaveDaysAction
{
    public function execute(int $staffProfileId, int $year, int $month): float
    {
        $start = Carbon::create($year, $month, 1, 0, 0, 0, 'Indian/Maldives')->toDateString();
        $end = Carbon::create($year, $month, 1, 0, 0, 0, 'Indian/Maldives')->endOfMonth()->toDateString();

        return (float) StaffAttendance::query()
            ->where('staff_profile_id', $staffProfileId)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->where('status', StaffAttendanceStatus::OnLeave)
            ->where('remarks', 'like', '%unpaid%')
            ->count();
    }
}
