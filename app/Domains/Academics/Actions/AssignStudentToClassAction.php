<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\ClassStudent;
use Illuminate\Support\Facades\DB;

class AssignStudentToClassAction
{
    public function execute(ClassRoom $class, int $studentId, ?string $enrolledAt = null): ClassStudent
    {
        return DB::transaction(function () use ($class, $studentId, $enrolledAt) {
            $row = ClassStudent::query()->updateOrCreate(
                [
                    'class_id' => $class->id,
                    'student_id' => $studentId,
                ],
                [
                    'academic_year_id' => $class->academic_year_id,
                    'enrolled_at' => $enrolledAt ?? now()->toDateString(),
                    'left_at' => null,
                    'status' => ClassStudentStatus::Active,
                ],
            );

            DB::table('students')->where('id', $studentId)->update([
                'class_id' => $class->id,
                'updated_at' => now(),
            ]);

            return $row;
        });
    }
}
