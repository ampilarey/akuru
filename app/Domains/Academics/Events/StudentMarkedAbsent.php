<?php

namespace App\Domains\Academics\Events;

use App\Domains\Academics\Enums\AttendanceStatus;

class StudentMarkedAbsent
{
    public function __construct(
        public int $studentId,
        public int $classAttendanceId,
        public string $date,
        public AttendanceStatus $status,
        public string $studentName,
    ) {}
}
