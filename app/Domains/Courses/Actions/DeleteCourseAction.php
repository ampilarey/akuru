<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §29's delete rule, as the only way a course may be removed.
 *
 * > Never hard-delete a course … if it has: Enrollments, Attendance records,
 * > Attempts, Progress records, Student submissions, Teacher feedback, Issued
 * > certificates, Payment records.
 * >
 * > Hard deletes are allowed only for draft content with no student activity
 * > and no dependent records.
 *
 * The destroy route called `$course->delete()` directly, and the foreign keys
 * turned that into something much worse than a missing row:
 *
 *   course_enrollments.course_id -> CASCADE
 *   payment_items.course_id      -> CASCADE
 *
 * A course with a roster and paid line items deleted cleanly and took both
 * away. Rule 12 says ledgers are append-only — reversals, not deletes — and a
 * cascade is the quietest delete there is.
 *
 * Now: a course with any dependent is **soft**-deleted, so it leaves the
 * catalogue while every enrolment, payment and progress row stays exactly where
 * it was. A genuinely empty draft is still removed outright, which is what §29
 * permits and what keeps the table from filling with abandoned drafts.
 */
class DeleteCourseAction
{
    /**
     * Tables that make a course part of somebody's history. Ordered as §29
     * lists them, so the two can be read side by side.
     *
     * @var array<string, string>
     */
    private const DEPENDENTS = [
        'course_enrollments' => 'enrolments',
        'attendance_records' => 'attendance records',
        'activity_attempts' => 'activity attempts',
        'assessment_attempts' => 'assessment attempts',
        'student_lesson_progress' => 'progress records',
        'issued_certificates' => 'issued certificates',
        'payment_items' => 'payment records',
    ];

    /**
     * @return array{soft: bool, blocked_by: array<string, int>}
     */
    public function execute(Course $course): array
    {
        $counts = $this->dependentCounts($course);

        if ($counts !== []) {
            $course->delete(); // soft: SoftDeletes is on the model

            return ['soft' => true, 'blocked_by' => $counts];
        }

        // Nothing of anyone's is attached. §29 allows the row to go.
        $course->forceDelete();

        return ['soft' => false, 'blocked_by' => []];
    }

    /**
     * Refuse rather than delete. Used where the caller wants §29's "never" to
     * be literal — a script, say — instead of degrading to a soft delete.
     *
     * @throws ValidationException
     */
    public function executeStrict(Course $course): void
    {
        $counts = $this->dependentCounts($course);

        if ($counts !== []) {
            $parts = [];
            foreach ($counts as $table => $count) {
                $parts[] = $count.' '.(self::DEPENDENTS[$table] ?? $table);
            }

            throw ValidationException::withMessages([
                'course' => 'This course has '.implode(', ', $parts).'. Historical student data must remain intact (SPEC §29).',
            ]);
        }

        $course->forceDelete();
    }

    /**
     * @return array<string, int> table => count, only for tables that exist and have rows
     */
    public function dependentCounts(Course $course): array
    {
        $counts = [];

        foreach (array_keys(self::DEPENDENTS) as $table) {
            // Tables are checked for existence because this list spans phases:
            // certificates arrived in Phase 3, payment items in Phase 4, and a
            // database part-way through migrations should not fatal here.
            if (! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'course_id')) {
                continue;
            }

            $count = DB::table($table)->where('course_id', $course->id)->count();
            if ($count > 0) {
                $counts[$table] = $count;
            }
        }

        return $counts;
    }
}
