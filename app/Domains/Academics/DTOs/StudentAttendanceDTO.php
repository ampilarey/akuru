<?php

namespace App\Domains\Academics\DTOs;

use App\Domains\Academics\Enums\AttendanceSource;
use App\Domains\Academics\Enums\AttendanceStatus;

final readonly class StudentAttendanceDTO
{
    public function __construct(
        public int $studentId,
        public int $classId,
        public int $academicYearId,
        public string $date,
        public AttendanceStatus $status,
        public AttendanceSource $source,
        public int $markedBy,
        public ?int $termId = null,
        public ?int $periodId = null,
        public ?int $lessonLogId = null,
        public ?int $minutesLate = null,
        public ?int $absenceNoteId = null,
        public ?string $remarks = null,
    ) {}
}
