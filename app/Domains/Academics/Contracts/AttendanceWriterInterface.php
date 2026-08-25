<?php

namespace App\Domains\Academics\Contracts;

use App\Domains\Academics\DTOs\StudentAttendanceDTO;
use App\Domains\Academics\Models\ClassAttendance;

interface AttendanceWriterInterface
{
    public function record(StudentAttendanceDTO $dto): ClassAttendance;
}
