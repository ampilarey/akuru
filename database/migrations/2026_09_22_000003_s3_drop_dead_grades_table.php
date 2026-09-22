<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The pre-S3 `grades` table, found dead by the 2026-09-22 S3 audit
 * (STATUS §5ey).
 *
 * `S3_SPEC` opens with *"`Grade` model exists as a thin stub with no exam
 * entity and no controllers"* and then builds the whole cycle beside it:
 * `exams`, `exam_marks`, `term_grades`, `grade_items`. Nothing ever wrote the
 * stub's table — zero rows on every deployment — and the only references left
 * were two `hasMany` relations on `Student` and `Teacher` that no code called.
 * Rule 9's "stop using, then drop" has had a month of nothing using it.
 * Refused if a row has appeared in the meantime rather than deleting anything
 * on a guess, the same as the `attendance` drop before it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('grades')) {
            return;
        }

        $rows = (int) DB::table('grades')->count();
        if ($rows > 0) {
            throw new RuntimeException(
                "grades has {$rows} row(s); it was believed unused. Look before dropping."
            );
        }

        Schema::drop('grades');
    }

    public function down(): void
    {
        // Forward-only (rule 9). The table was empty.
    }
};
