<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use Illuminate\Support\Facades\DB;

/**
 * BLOG_PLAN BL2a: the account of a student's class teacher, who checks the
 * student's blog posts first. The class the student is on now (an active
 * `class_student` row) wins; the record's own `students.class_id` is the
 * fallback. `classes.class_teacher_id` stores a `users.id`. Null when the
 * student has no class or the class has no class teacher — a course
 * learner's post goes straight to the office.
 */
class ResolveClassTeacherForStudentAction
{
    public function execute(int $studentId): ?int
    {
        $classIds = DB::table('class_student')
            ->where('student_id', $studentId)
            ->where('status', ClassStudentStatus::Active->value)
            ->orderByDesc('id')
            ->pluck('class_id')
            ->all();
        $own = DB::table('students')->where('id', $studentId)->value('class_id');
        if ($own !== null) {
            $classIds[] = $own;
        }

        foreach ($classIds as $classId) {
            $teacher = DB::table('classes')->where('id', $classId)->value('class_teacher_id');
            if ($teacher !== null) {
                return (int) $teacher;
            }
        }

        return null;
    }
}
