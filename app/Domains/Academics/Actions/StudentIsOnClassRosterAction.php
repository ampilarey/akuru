<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\Academics\Models\ClassStudent;

class StudentIsOnClassRosterAction
{
    public function execute(int $studentId, int $classId): bool
    {
        return ClassStudent::query()
            ->where('class_id', $classId)
            ->where('student_id', $studentId)
            ->where('status', ClassStudentStatus::Active->value)
            ->exists();
    }
}
