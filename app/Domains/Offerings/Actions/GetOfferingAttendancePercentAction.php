<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Enums\AttendanceStatus;
use App\Domains\Offerings\Models\AttendanceRecord;
use App\Domains\Offerings\Models\CourseOfferingSession;

class GetOfferingAttendancePercentAction
{
    public function execute(int $offeringId, int $studentId): ?int
    {
        $total = CourseOfferingSession::query()
            ->where('course_offering_id', $offeringId)
            ->count();
        if ($total < 1) {
            return null;
        }

        $present = AttendanceRecord::query()
            ->where('course_offering_id', $offeringId)
            ->where('student_id', $studentId)
            ->whereIn('status', [
                AttendanceStatus::Present,
                AttendanceStatus::Late,
                AttendanceStatus::Excused,
            ])
            ->count();

        return (int) round(100 * $present / $total);
    }
}
