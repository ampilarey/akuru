<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\StaffAttendanceStatus;
use App\Domains\HR\Models\StaffAttendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * How many days of unpaid leave a staff member took in a month — the number
 * `MaldivesPayrollCalculator` multiplies by `basic_salary / working_days` to
 * deduct from a payslip.
 *
 * It used to be `where('remarks', 'like', '%unpaid%')->count()`: a salary
 * deduction decided by a substring of a free-text note. `leave_types.paid` is a
 * boolean `ApproveStaffLeaveAction` has in hand and spent writing the sentence
 * "Approved unpaid leave", which this then parsed back. Three things followed:
 *
 *  - **A half day cost a whole day's pay.** `'Half-day unpaid leave'` matches
 *    `%unpaid%`, and `count()` counts rows, so the half the rest of the system
 *    is careful about (`CountLeaveDaysAction` returns 0.5) was lost here.
 *  - **The platform is trilingual.** A remark written in Dhivehi or Arabic
 *    never matches, so unpaid leave silently becomes paid.
 *  - **Any note containing the word deducted pay** — including one saying the
 *    leave was *not* unpaid — and editing a note changed someone's salary.
 *
 * The fact now travels as `leave_paid` and `leave_day_fraction` (rule 11). The
 * remark is a note for a human again.
 */
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
            ->where('leave_paid', false)
            // A row recorded before this column existed was backfilled; one
            // written without a fraction is a whole day, which is what every
            // such row meant when it was written.
            ->sum(DB::raw('COALESCE(leave_day_fraction, 1)'));
    }
}
