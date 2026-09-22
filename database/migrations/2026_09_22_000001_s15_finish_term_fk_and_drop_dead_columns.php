<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S1.5, finished: the enrolment term becomes a real foreign key (ADR-037).
 *
 * The 2026-08-23 backbone migration added `course_enrollments.unified_term_id`
 * (FK → `terms`) beside the bare `term_id` and backfilled it once — rule 9's
 * deploy 1. Deploy 2, switching reads and writes to it, never happened: a
 * month later nothing in the application reads or writes `unified_term_id`,
 * registration still writes `term_id` from an unvalidated request field, and
 * `term_id` is an `int(10) unsigned` that references nothing.
 *
 * Rather than finish the switch onto a second column nobody uses, the FK goes
 * on `term_id` itself and the dead column is dropped:
 *
 * 1. Any `term_id` that points at no `terms` row takes the row's
 *    `unified_term_id` (the Legacy term the backfill assigned it), else NULL.
 * 2. `term_id` is widened to `bigint unsigned` — MySQL requires the FK column
 *    to match `terms.id` exactly — and constrained to `terms`, `ON DELETE
 *    RESTRICT`: a term with enrolments cannot be deleted, and deleting a term
 *    can never delete an enrolment.
 * 3. `unified_term_id` is dropped. Never read, so "stop reading, then drop"
 *    collapses to one deploy — the reading step had nothing to stop.
 *
 * `term_key` **stays**, against the spec's "drop generated term_key". It is
 * `IFNULL(term_id, 0)` and it is the only reason the unique key
 * `(student_id, course_id, term_key)` catches a second course-only enrolment:
 * MySQL treats NULLs as distinct in a unique index, so on `term_id` alone two
 * NULL-term rows for the same pupil and course would both insert. #397 leans
 * on it (`CreateOrReviveEnrollmentAction`). It has to be dropped and recreated
 * here only because MySQL will not `MODIFY` a column a generated column
 * depends on.
 *
 * Also dropped: `academic_years.terms`, the JSON column the `terms` table
 * replaced. The backfill read it on 2026-08-23; nothing has since.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Orphans → the Legacy term the earlier backfill chose, else NULL.
        DB::table('course_enrollments')
            ->whereNotNull('term_id')
            ->whereNotIn('term_id', DB::table('terms')->select('id'))
            ->update(['term_id' => DB::raw('unified_term_id')]);

        DB::table('course_enrollments')
            ->whereNotNull('term_id')
            ->whereNotIn('term_id', DB::table('terms')->select('id'))
            ->update(['term_id' => null]);

        // 2. Widen and constrain. The generated column and its unique key come
        //    off first and go straight back on; a collision here (two
        //    enrolments now sharing student, course and term) is a deploy
        //    stopped loudly, not a row quietly rewritten.
        //
        //    The unique key's leading column is `student_id`, and MySQL has been
        //    using it as the index behind the `student_id` FK (there is no other
        //    index on that column), so the FK has to come off before the key can
        //    and goes back on once the key is rebuilt.
        DB::statement('ALTER TABLE course_enrollments DROP FOREIGN KEY course_enrollments_student_id_foreign');
        DB::statement('ALTER TABLE course_enrollments DROP INDEX course_enrollments_student_course_term_unique');
        DB::statement('ALTER TABLE course_enrollments DROP COLUMN term_key');
        DB::statement('ALTER TABLE course_enrollments MODIFY term_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE course_enrollments ADD COLUMN term_key BIGINT GENERATED ALWAYS AS (IFNULL(term_id, 0)) STORED');
        DB::statement('ALTER TABLE course_enrollments ADD UNIQUE KEY course_enrollments_student_course_term_unique (student_id, course_id, term_key)');
        DB::statement('ALTER TABLE course_enrollments ADD CONSTRAINT course_enrollments_student_id_foreign FOREIGN KEY (student_id) REFERENCES registration_students (id) ON DELETE CASCADE');
        //
        //    RESTRICT, not SET NULL: MariaDB refuses a cascading/nulling FK on a
        //    column a generated column is built from (error 1901), and a term
        //    that still has enrolments should not be deletable anyway — the
        //    enrolment is the record of what happened in that term.
        DB::statement('ALTER TABLE course_enrollments ADD CONSTRAINT course_enrollments_term_id_foreign FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE RESTRICT');

        // 3. The column the switch never reached.
        if (Schema::hasColumn('course_enrollments', 'unified_term_id')) {
            DB::statement('ALTER TABLE course_enrollments DROP FOREIGN KEY course_enrollments_unified_term_id_foreign');
            DB::statement('ALTER TABLE course_enrollments DROP COLUMN unified_term_id');
        }

        if (Schema::hasColumn('academic_years', 'terms')) {
            DB::statement('ALTER TABLE academic_years DROP COLUMN terms');
        }
    }

    public function down(): void
    {
        // Rule 9: forward-only for populated tables. The FK and the widened
        // type are safe to leave in place; the dropped columns were unread.
    }
};
