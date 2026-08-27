<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase F3 — Qur'an component tables (SPEC §52.19–52.22), keyed to ENGINE ids:
 * course_enrollments, unified students, existing surahs (rule 11 — no parallel
 * Quran source tables) and the Arabic component's letters/harakas (table-level
 * FKs only; no cross-component code reference). ai_prediction_id is reserved
 * per spec — nothing writes it before the Pronunciation phases (rule 8).
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_recitation_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_enrollment_id')->constrained('course_enrollments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('surah_id')->nullable()->constrained('surahs')->nullOnDelete();
            $table->unsignedSmallInteger('start_ayah_number')->nullable();
            $table->unsignedSmallInteger('end_ayah_number')->nullable();
            $table->foreignId('audio_media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->string('mode', 20)->default('manual');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('submitted_at');
            $table->string('status', 40)->default('submitted');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->unsignedBigInteger('ai_prediction_id')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'submitted_at']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('quran_mistake_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quran_recitation_submission_id')->constrained('quran_recitation_submissions')->cascadeOnDelete();
            $table->foreignId('surah_id')->nullable()->constrained('surahs')->nullOnDelete();
            $table->unsignedSmallInteger('ayah_number')->nullable();
            $table->unsignedSmallInteger('word_position')->nullable();
            $table->foreignId('expected_letter_id')->nullable()->constrained('arabic_letters')->nullOnDelete();
            $table->foreignId('expected_haraka_id')->nullable()->constrained('arabic_harakas')->nullOnDelete();
            $table->foreignId('predicted_letter_id')->nullable()->constrained('arabic_letters')->nullOnDelete();
            $table->foreignId('predicted_haraka_id')->nullable()->constrained('arabic_harakas')->nullOnDelete();
            $table->string('mistake_type', 40);
            $table->string('severity', 20)->default('minor');
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->unsignedInteger('audio_start_ms')->nullable();
            $table->unsignedInteger('audio_end_ms')->nullable();
            $table->timestamps();
        });

        Schema::create('quran_revision_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('surah_id')->nullable()->constrained('surahs')->nullOnDelete();
            $table->unsignedSmallInteger('start_ayah_number')->nullable();
            $table->unsignedSmallInteger('end_ayah_number')->nullable();
            $table->date('scheduled_date');
            $table->string('frequency', 40)->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'scheduled_date']);
        });

        Schema::create('quran_memorization_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('surah_id')->nullable()->constrained('surahs')->nullOnDelete();
            $table->unsignedSmallInteger('start_ayah_number')->nullable();
            $table->unsignedSmallInteger('end_ayah_number')->nullable();
            $table->string('status', 20)->default('not_started');
            $table->timestamp('last_reviewed_at')->nullable();
            $table->unsignedTinyInteger('strength_score')->nullable();
            $table->unsignedInteger('mistake_count')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'surah_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_memorization_progress');
        Schema::dropIfExists('quran_revision_schedules');
        Schema::dropIfExists('quran_mistake_marks');
        Schema::dropIfExists('quran_recitation_submissions');
    }
};
