<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\StaffAttendanceStatus;
use App\Domains\HR\Models\StaffAttendance;
use Carbon\Carbon;

class SummarizeStaffAttendanceMonthAction
{
    /**
     * @return array{
     *     staff_profile_id: int,
     *     year: int,
     *     month: int,
     *     present: int,
     *     absent: int,
     *     late: int,
     *     half_day: int,
     *     on_leave: int,
     *     holiday: int,
     *     minutes_late: int,
     *     working_days: int
     * }
     */
    public function execute(int $staffProfileId, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1, 0, 0, 0, 'Indian/Maldives')->toDateString();
        $end = Carbon::create($year, $month, 1, 0, 0, 0, 'Indian/Maldives')->endOfMonth()->toDateString();

        $rows = StaffAttendance::query()
            ->where('staff_profile_id', $staffProfileId)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->get();

        $counts = [
            StaffAttendanceStatus::Present->value => 0,
            StaffAttendanceStatus::Absent->value => 0,
            StaffAttendanceStatus::Late->value => 0,
            StaffAttendanceStatus::HalfDay->value => 0,
            StaffAttendanceStatus::OnLeave->value => 0,
            StaffAttendanceStatus::Holiday->value => 0,
        ];

        $minutesLate = 0;
        foreach ($rows as $row) {
            $status = $row->status instanceof StaffAttendanceStatus ? $row->status->value : (string) $row->status;
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
            $minutesLate += (int) ($row->minutes_late ?? 0);
        }

        $workingDays = $counts[StaffAttendanceStatus::Present->value]
            + $counts[StaffAttendanceStatus::Late->value]
            + $counts[StaffAttendanceStatus::HalfDay->value]
            + $counts[StaffAttendanceStatus::Absent->value]
            + $counts[StaffAttendanceStatus::OnLeave->value];

        return [
            'staff_profile_id' => $staffProfileId,
            'year' => $year,
            'month' => $month,
            'present' => $counts[StaffAttendanceStatus::Present->value],
            'absent' => $counts[StaffAttendanceStatus::Absent->value],
            'late' => $counts[StaffAttendanceStatus::Late->value],
            'half_day' => $counts[StaffAttendanceStatus::HalfDay->value],
            'on_leave' => $counts[StaffAttendanceStatus::OnLeave->value],
            'holiday' => $counts[StaffAttendanceStatus::Holiday->value],
            'minutes_late' => $minutesLate,
            'working_days' => $workingDays,
        ];
    }
}
