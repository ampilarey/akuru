<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 leftover — attach assessments to a class or a course, and hold
 * legacy quiz/assignment ids so backfill can run without dropping old tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->nullable()->change();
            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
            $table->unsignedBigInteger('classroom_id')->nullable()->after('course_id');
            $table->unsignedBigInteger('academic_year_id')->nullable()->after('classroom_id');
            $table->unsignedBigInteger('term_id')->nullable()->after('academic_year_id');
            $table->unsignedBigInteger('legacy_quiz_id')->nullable()->unique();
            $table->unsignedBigInteger('legacy_assignment_id')->nullable()->unique();

            $table->index('classroom_id');
            $table->index('academic_year_id');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_quiz_question_id')->nullable()->unique();
            $table->unsignedBigInteger('legacy_assignment_id')->nullable()->index();
        });

        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->unsignedBigInteger('enrollment_id')->nullable()->change();
            $table->unsignedBigInteger('course_id')->nullable()->change();
            $table->unsignedBigInteger('classroom_id')->nullable()->after('course_id');
            $table->unsignedBigInteger('legacy_quiz_attempt_id')->nullable()->unique();
            $table->unsignedBigInteger('legacy_assignment_submission_id')->nullable()->unique();
            $table->index('classroom_id');
            $table->unique(['student_id', 'assessment_id', 'attempt_number'], 'assessment_attempts_student_assessment_number_uq');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->dropUnique('assessment_attempts_student_assessment_number_uq');
            $table->dropUnique(['legacy_quiz_attempt_id']);
            $table->dropUnique(['legacy_assignment_submission_id']);
            $table->dropIndex(['classroom_id']);
            $table->dropColumn([
                'classroom_id',
                'legacy_quiz_attempt_id',
                'legacy_assignment_submission_id',
            ]);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropUnique(['legacy_quiz_question_id']);
            $table->dropIndex(['legacy_assignment_id']);
            $table->dropColumn(['legacy_quiz_question_id', 'legacy_assignment_id']);
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropUnique(['legacy_quiz_id']);
            $table->dropUnique(['legacy_assignment_id']);
            $table->dropIndex(['classroom_id']);
            $table->dropIndex(['academic_year_id']);
            $table->dropColumn([
                'classroom_id',
                'academic_year_id',
                'term_id',
                'legacy_quiz_id',
                'legacy_assignment_id',
            ]);
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id')->nullable(false)->change();
            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
        });
    }
};
