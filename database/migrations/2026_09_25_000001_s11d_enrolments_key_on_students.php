<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * S1 Deploy 3, slice 1: an enrolment is one per *student*, not one per
 * legacy registration row (OWNER_ACTIONS item 10, confirmed 2026-09-24;
 * `docs/migrations/s11-deploy-3-cleanup-proposal.md`).
 *
 * Deploy 2 switched every read to `course_enrollments.unified_student_id`,
 * but the rule that stops a second enrolment still lived on the legacy
 * column: `UNIQUE (student_id, course_id, term_key)`, where `student_id`
 * references `registration_students`. The next slice stops writing that
 * column, and a key on a column nobody writes stops nothing.
 *
 * So, additive only (rule 9's first deploy, even though its waits are
 * optional until first real use):
 *
 * 1. Any enrolment still missing `unified_student_id` takes it from the
 *    student its legacy row was unified into. Deploy 2's backfill and the
 *    model's saving hook should have left none; this is the belt.
 * 2. A deploy that would put two live enrolments on the same student,
 *    course and term **stops loudly** and names them. It never picks one.
 * 3. `UNIQUE (unified_student_id, course_id, term_key)` goes on. `term_key`
 *    is `IFNULL(term_id, 0)`, for the reason the S1.5 migration gives: a
 *    NULL term is distinct from every other NULL in a unique index.
 * 4. `student_id` becomes nullable, so the next slice can stop writing it.
 *    Its foreign key and the old unique key stay until the legacy table is
 *    archived (slice 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Belt: fill the unified id from the legacy map where it is missing.
        DB::statement(
            'UPDATE course_enrollments ce
             JOIN students s ON s.legacy_registration_student_id = ce.student_id
             SET ce.unified_student_id = s.id
             WHERE ce.unified_student_id IS NULL'
        );

        // 2. Gate: two rows for one student, course and term cannot both be
        //    kept by the new key, and choosing between them is a person's job.
        $collisions = DB::table('course_enrollments')
            ->whereNotNull('unified_student_id')
            ->select('unified_student_id', 'course_id', 'term_key', DB::raw('GROUP_CONCAT(id ORDER BY id) AS ids'))
            ->groupBy('unified_student_id', 'course_id', 'term_key')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($collisions->isNotEmpty()) {
            $lines = $collisions->map(fn ($row) => "student {$row->unified_student_id}, course {$row->course_id}, term key {$row->term_key}: enrolments {$row->ids}")
                ->implode('; ');

            throw new RuntimeException(
                'Deploy 3 slice 1 stopped: these enrolments share one student, course and term, '
                ."and the new key keeps one row each. Merge or remove the extras first. {$lines}"
            );
        }

        // 3. The key, on the student every read already uses.
        DB::statement('ALTER TABLE course_enrollments ADD UNIQUE KEY course_enrollments_unified_student_course_term_unique (unified_student_id, course_id, term_key)');

        // 4. Optional legacy id. The FK is dropped and restored around the
        //    change because MySQL will not modify a column under a constraint.
        DB::statement('ALTER TABLE course_enrollments DROP FOREIGN KEY course_enrollments_student_id_foreign');
        DB::statement('ALTER TABLE course_enrollments MODIFY student_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE course_enrollments ADD CONSTRAINT course_enrollments_student_id_foreign FOREIGN KEY (student_id) REFERENCES registration_students (id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        // MySQL retires the unified FK's own implicit index once the new key
        // can serve it, so the key cannot be dropped under the constraint.
        // Re-adding the FK recreates an index for it.
        DB::statement('ALTER TABLE course_enrollments DROP FOREIGN KEY course_enrollments_unified_student_id_foreign');
        DB::statement('ALTER TABLE course_enrollments DROP INDEX course_enrollments_unified_student_course_term_unique');
        DB::statement('ALTER TABLE course_enrollments ADD CONSTRAINT course_enrollments_unified_student_id_foreign FOREIGN KEY (unified_student_id) REFERENCES students (id) ON DELETE SET NULL');

        // Only reversible while every row still carries its legacy id.
        if (DB::table('course_enrollments')->whereNull('student_id')->exists()) {
            return;
        }

        DB::statement('ALTER TABLE course_enrollments DROP FOREIGN KEY course_enrollments_student_id_foreign');
        DB::statement('ALTER TABLE course_enrollments MODIFY student_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE course_enrollments ADD CONSTRAINT course_enrollments_student_id_foreign FOREIGN KEY (student_id) REFERENCES registration_students (id) ON DELETE CASCADE');
    }
};
