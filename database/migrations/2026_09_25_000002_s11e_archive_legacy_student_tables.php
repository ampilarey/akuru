<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S1 Deploy 3, slice 3: the legacy student tables are retired
 * (OWNER_ACTIONS item 10; `docs/migrations/s11-deploy-3-cleanup-proposal.md`
 * steps 5–8).
 *
 * Slice 1 moved the enrolment key onto `unified_student_id`; slice 2 stopped
 * every write into `registration_students` and `student_guardians`. What is
 * left is history, and history is **archived, not dropped** (S1_SPEC):
 *
 * 1. **Belt.** Any enrolment or payment missing `unified_student_id` takes it
 *    from the student its legacy row was unified into.
 * 2. `course_enrollments.student_id` and `payments.student_id` lose their
 *    foreign keys (and the enrolment's old unique key) and are renamed
 *    `archived_registration_student_id`. Nothing reads them; a row the
 *    unification could never place keeps the pointer it had.
 * 3. `student_guardians` becomes `archived_student_guardians`.
 *    `guardian_student` holds every link the product reads.
 * 4. `registration_students` becomes `archived_registration_students`, and
 *    gains `unified_student_id` so each archived row still says which student
 *    it became.
 * 5. `students.legacy_registration_student_id` goes; step 4 carried the
 *    mapping across first.
 *
 * **Why archive rather than drop, against the proposal's "drop".** The
 * proposal planned gates that stop the deploy on an enrolment, payment or
 * guardian link the unification never placed. On staging such rows are
 * known to have existed (STATUS: "collisions + orphan guardians"), and the
 * staging deploy runs this migration inside its chain: a gate that throws
 * there leaves new code on an old schema. Renaming keeps every row, needs no
 * gate, and leaves "drop the archive" as a later, separate decision.
 *
 * `down()` reverses the renames and restores the mapping column from the
 * archive, so a rollback loses nothing this migration kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Belt.
        foreach (['course_enrollments', 'payments'] as $table) {
            DB::statement(
                "UPDATE {$table} t
                 JOIN students s ON s.legacy_registration_student_id = t.student_id
                 SET t.unified_student_id = s.id
                 WHERE t.unified_student_id IS NULL AND t.student_id IS NOT NULL"
            );
        }

        // 2. The legacy pointers on enrolments and payments, kept as history.
        DB::statement('ALTER TABLE course_enrollments DROP FOREIGN KEY course_enrollments_student_id_foreign');
        DB::statement('ALTER TABLE course_enrollments DROP INDEX course_enrollments_student_course_term_unique');
        DB::statement('ALTER TABLE course_enrollments CHANGE student_id archived_registration_student_id BIGINT UNSIGNED NULL');

        DB::statement('ALTER TABLE payments DROP FOREIGN KEY payments_student_id_foreign');
        DB::statement('ALTER TABLE payments CHANGE student_id archived_registration_student_id BIGINT UNSIGNED NULL');

        // 3 and 4. Archive the two tables together; the foreign key between
        //    them follows the rename.
        Schema::table('registration_students', function (Blueprint $table) {
            $table->unsignedBigInteger('unified_student_id')->nullable()->after('id');
        });
        DB::statement(
            'UPDATE registration_students rs
             JOIN students s ON s.legacy_registration_student_id = rs.id
             SET rs.unified_student_id = s.id'
        );
        Schema::rename('student_guardians', 'archived_student_guardians');
        Schema::rename('registration_students', 'archived_registration_students');

        // 5. The transition key.
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique('students_legacy_registration_student_id_unique');
            $table->dropColumn('legacy_registration_student_id');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_registration_student_id')->nullable()->unique();
        });

        Schema::rename('archived_registration_students', 'registration_students');
        Schema::rename('archived_student_guardians', 'student_guardians');
        DB::statement(
            'UPDATE students s
             JOIN registration_students rs ON rs.unified_student_id = s.id
             SET s.legacy_registration_student_id = rs.id'
        );
        Schema::table('registration_students', function (Blueprint $table) {
            $table->dropColumn('unified_student_id');
        });

        DB::statement('ALTER TABLE payments CHANGE archived_registration_student_id student_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_student_id_foreign FOREIGN KEY (student_id) REFERENCES registration_students (id) ON DELETE SET NULL');

        DB::statement('ALTER TABLE course_enrollments CHANGE archived_registration_student_id student_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE course_enrollments ADD UNIQUE KEY course_enrollments_student_course_term_unique (student_id, course_id, term_key)');
        DB::statement('ALTER TABLE course_enrollments ADD CONSTRAINT course_enrollments_student_id_foreign FOREIGN KEY (student_id) REFERENCES registration_students (id) ON DELETE CASCADE');
    }
};
