<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC §29 — "Use soft deletes on: Courses … Enrollments" and "Never
 * hard-delete a course … if it has enrollments, attendance records, attempts,
 * progress records … payment records."
 *
 * Six of the eight models §29 names already soft-delete. `Course` and
 * `CourseEnrollment` did not, and `admin.courses.destroy` hard-deletes an
 * engine `Course` with no dependency check at all. The foreign keys make that
 * worse than an ordinary delete:
 *
 *   course_enrollments.course_id -> CASCADE
 *   payment_items.course_id      -> CASCADE
 *
 * so removing a course silently takes the whole roster **and its payment line
 * items** with it. Rule 12 is explicit that ledger tables are append-only —
 * reversals, not deletes — and a cascade is the most silent delete there is.
 *
 * Additive only (rule 9): two nullable columns, nothing dropped or renamed. On
 * existing data every row keeps `deleted_at` null, so no query changes meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
