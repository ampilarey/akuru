<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2.3 — assessments, question pivot, and attempt snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->unsignedBigInteger('course_module_id')->nullable();
            $table->unsignedBigInteger('lesson_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('assessment_type', 32)->default('lesson_quiz');
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->unsignedInteger('passing_score')->nullable();
            $table->unsignedInteger('max_score')->default(0);
            $table->unsignedTinyInteger('retake_limit')->nullable();
            $table->boolean('randomize_questions')->default(false);
            $table->boolean('show_results')->default(true);
            $table->boolean('show_correct_answers')->default(false);
            $table->boolean('requires_teacher_marking')->default(false);
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'status']);
        });

        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->restrictOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->unsignedInteger('points_override')->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['assessment_id', 'question_id']);
        });

        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedBigInteger('enrollment_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->string('status', 20)->default('in_progress');
            $table->json('answers')->nullable();
            $table->json('snapshots')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->unsignedInteger('max_score')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'assessment_id', 'status']);
            $table->unique(['enrollment_id', 'assessment_id', 'attempt_number'], 'assessment_attempts_enroll_assessment_number_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempts');
        Schema::dropIfExists('assessment_questions');
        Schema::dropIfExists('assessments');
    }
};
