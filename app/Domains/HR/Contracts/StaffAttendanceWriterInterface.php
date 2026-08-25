<?php

namespace App\Domains\HR\Contracts;

use App\Domains\HR\DTOs\StaffAttendanceDTO;
use App\Domains\HR\Models\StaffAttendance;

interface StaffAttendanceWriterInterface
{
    public function record(StaffAttendanceDTO $dto): StaffAttendance;
}
