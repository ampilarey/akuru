<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SPEC §25 lists `course offering ID nullable` on `student_lesson_progress`,
 * and says offering progress "should be calculated in the context of that
 * offering".
 *
 * The column existed and `RecordLessonProgressAction` accepted it. Its only
 * caller never sent it, so **every** progress row was null — including rows
 * belonging to students enrolled through a live batch. This repairs the rows
 * written before the caller was fixed.
 *
 * Safe by construction, for the same reasons §14's resync was:
 *
 *   - It only copies a value from the enrolment that already owns the row.
 *     `enrollment_id` is not touched; nothing is created or deleted; every
 *     value written is already derivable from what is read.
 *   - It is idempotent, and only touches rows that are actually null, so on a
 *     deployment with no offering enrolments it is a no-op.
 *   - Rule 9 guards dropping or renaming a populated column. This populates a
 *     column that has never held anything, which is the opposite.
 *
 * Rows whose enrolment has no offering stay null, which is what the field's
 * "nullable" means: self-learning progress belongs to no batch.
 */
return new class extends Migration
{
    public function up(): void
    {
        $missing = DB::table('student_lesson_progress')
            ->join('course_enrollments', 'course_enrollments.id', '=', 'student_lesson_progress.enrollment_id')
            ->whereNull('student_lesson_progress.course_offering_id')
            ->whereNotNull('course_enrollments.course_offering_id')
            ->count();

        if ($missing === 0) {
            return;
        }

        DB::statement('
            UPDATE student_lesson_progress
            JOIN course_enrollments ON course_enrollments.id = student_lesson_progress.enrollment_id
            SET student_lesson_progress.course_offering_id = course_enrollments.course_offering_id,
                student_lesson_progress.updated_at = NOW()
            WHERE student_lesson_progress.course_offering_id IS NULL
              AND course_enrollments.course_offering_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        // Deliberately irreversible. The "previous" value was null because
        // nothing ever wrote it, not because null was a decision, and blanking
        // the column again would only restore the defect.
    }
};
