<?php

namespace App\Domains\HR\DTOs;

use App\Domains\HR\Enums\StaffAttendanceSource;
use App\Domains\HR\Enums\StaffAttendanceStatus;

final readonly class StaffAttendanceDTO
{
    public function __construct(
        public int $staffProfileId,
        public int $academicYearId,
        public string $date,
        public StaffAttendanceStatus $status,
        public StaffAttendanceSource $source,
        public ?int $markedBy = null,
        public ?string $checkIn = null,
        public ?string $checkOut = null,
        public ?int $minutesLate = null,
        public ?string $remarks = null,
    ) {}
}
