<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1A.6 — self-learning enrollment fields + student_lesson_progress.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->string('enrollment_type', 20)->default('free')->after('status');
            $table->unsignedTinyInteger('progress_percentage')->default(0)->after('enrollment_type');
            $table->timestamp('completed_at')->nullable()->after('enrolled_at');
        });

        Schema::create('student_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('course_enrollments')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->unsignedBigInteger('course_offering_id')->nullable();
            $table->foreignId('course_module_id')->constrained('course_modules')->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->restrictOnDelete();
            $table->foreignId('lesson_revision_id')->constrained('lesson_revisions')->restrictOnDelete();
            $table->unsignedBigInteger('student_id');
            $table->string('status', 20)->default('in_progress');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('score_summary')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'lesson_id'], 'student_lesson_progress_enrollment_lesson_uq');
            $table->index(['student_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_lesson_progress');
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropColumn(['enrollment_type', 'progress_percentage', 'completed_at']);
        });
    }
};
