<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * The indexes added for columns the application actually filters on.
 *
 * This is a schema assertion rather than a benchmark: it says the index is
 * there, not that a query got faster. Timing anything on a seeded fixture
 * would measure the fixture.
 *
 * ## Why these twelve and not the other twenty
 *
 * 34 `*_id` columns appear in no index at all. Indexing all of them would be
 * the easy answer and the wrong one — every index is paid for on each insert,
 * and these are the tables that take the most writes. Each column here is
 * backed by an actual `where()` on it **in code that touches that table**.
 *
 * The twenty that are never filtered are deliberately left bare, including
 * several that read as obviously hot by name:
 * `attendance_records.academic_year_id`, `course_enrollments.term_id`,
 * `issued_certificates.enrollment_id`. Adding an index for a query nobody
 * writes is a cost with no reader.
 *
 * That check also corrected an assumption: `attendance_records` is the
 * highest-volume table in a school system, and its genuinely hot filters —
 * `enrollment_id`, `course_offering_session_id`, `course_offering_id` — were
 * **already** indexed. Only `student_id` was not.
 */
it('indexes the foreign keys the code filters on', function () {
    $expected = [
        'assessment_attempts' => ['course_id'],
        'activity_attempts' => ['course_id', 'student_id'],
        'attendance_records' => ['student_id'],
        'activities' => ['lesson_id'],
        'assessments' => ['term_id', 'course_module_id'],
        'issued_certificates' => ['course_id'],
        'course_offerings' => ['academic_year_id'],
        'discount_redemptions' => ['purchase_id'],
        'library_items' => ['writer_id'],
        'writer_earnings' => ['writer_payout_id'],
    ];

    $missing = [];

    foreach ($expected as $table => $columns) {
        expect(Schema::hasTable($table))->toBeTrue("Table {$table} is gone — update this list.");

        $leads = collect(Schema::getIndexes($table))
            ->map(fn (array $index) => $index['columns'][0] ?? null)
            ->filter()
            ->all();

        foreach ($columns as $column) {
            if (! in_array($column, $leads, true)) {
                $missing[] = $table.'.'.$column;
            }
        }
    }

    expect($missing)->toBeEmpty(
        "These lost the index added for them:\n  ".implode("\n  ", $missing)
        ."\n\nEach was added because the code filters on it. If the query has gone, "
        .'remove the column from this list and from the migration together.'
    );
});
