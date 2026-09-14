<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for foreign keys the application actually filters on.
 *
 * ## How these were chosen, and what was deliberately left out
 *
 * 593 `*_id` columns exist. 34 of them appear in **no index at all** once
 * `national_id` (an identity document, not a key) and the morph columns
 * (indexed as the second half of a `(type, id)` composite) are set aside.
 *
 * Indexing all 34 would be the easy answer and the wrong one: every index is
 * paid for on every insert and update, and these are the tables that take the
 * most writes. So each column here is backed by an actual `where()` on that
 * column **in code that touches that table** — not by the column's name, and
 * not by a count of the name across the whole app, which overstated this
 * badly on the first pass.
 *
 * **Twenty columns were found never to be filtered and are not indexed**,
 * including several that looked obviously "hot" by name:
 * `attendance_records.academic_year_id`, `course_enrollments.term_id`,
 * `issued_certificates.enrollment_id`. If a query for one appears later, the
 * index belongs in that slice, with the query that justifies it.
 *
 * The same check corrected an assumption worth recording: `attendance_records`
 * is the highest-volume table in a school system, and its genuinely hot
 * filters — `enrollment_id`, `course_offering_session_id`,
 * `course_offering_id` — **were already indexed**. Only `student_id` was not.
 *
 * ## Safety
 *
 * Purely additive (rule 9): no column is added, changed or dropped and no data
 * moves. Adding an index locks the table briefly on MySQL 8 for the duration
 * of the build; on a dataset this size that is milliseconds, and there is no
 * production data yet (ADR-021).
 */
return new class extends Migration
{
    /**
     * table => [column, ...]
     *
     * @var array<string, list<string>>
     */
    private array $indexes = [
        // Per-student-per-attempt tables: the ones that actually grow.
        'assessment_attempts' => ['course_id'],
        'activity_attempts' => ['course_id', 'student_id'],
        'attendance_records' => ['student_id'],

        // Read on every lesson the player opens.
        'activities' => ['lesson_id'],
        'assessments' => ['term_id', 'course_module_id'],

        // Moderate tables, each with a filter in code.
        'issued_certificates' => ['course_id'],
        'course_offerings' => ['academic_year_id'],
        'discount_redemptions' => ['purchase_id'],
        'library_items' => ['writer_id'],
        'writer_earnings' => ['writer_payout_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                foreach ($columns as $column) {
                    // Tolerant of a column that has since gone, and of an
                    // index somebody added in the meantime: this migration
                    // must not be the reason a deploy stops.
                    if (Schema::hasColumn($table, $column) && ! $this->hasIndex($table, $column)) {
                        $blueprint->index($column);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                foreach ($columns as $column) {
                    if ($this->hasIndex($table, $column)) {
                        $blueprint->dropIndex($table.'_'.$column.'_index');
                    }
                }
            });
        }
    }

    /**
     * Whether an index already leads with this column.
     */
    private function hasIndex(string $table, string $column): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => ($index['columns'][0] ?? null) === $column);
    }
};
