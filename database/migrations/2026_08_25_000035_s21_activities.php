<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2.1 — activity patterns and attempts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->unsignedBigInteger('course_module_id')->nullable();
            $table->unsignedBigInteger('lesson_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('pattern', 32);
            $table->string('activity_type', 64);
            $table->json('data');
            $table->json('settings')->nullable();
            $table->unsignedInteger('max_score')->default(1);
            $table->unsignedInteger('passing_score')->nullable();
            $table->boolean('is_required')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'pattern']);
        });

        Schema::create('activity_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('activity_id');
            $table->unsignedBigInteger('enrollment_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->string('status', 20)->default('in_progress');
            $table->json('answers')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->unsignedInteger('max_score')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'activity_id', 'status']);
            $table->unique(['enrollment_id', 'activity_id', 'attempt_number'], 'activity_attempts_enroll_activity_number_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_attempts');
        Schema::dropIfExists('activities');
    }
};
