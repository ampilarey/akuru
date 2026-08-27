<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5 parity slice P1 (ADR-025 gate item 1): the legacy three-lane Hifz
 * session record — new memorization / recent revision / old revision with
 * scores and mistake breakdowns — re-keyed to ENGINE ids. One row per
 * (engine session, enrollment). Attendance is NOT duplicated here: it stays
 * in attendance_records, written through RecordOfferingAttendanceAction
 * (single source). Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_session_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_session_id')->constrained('course_offering_sessions')->cascadeOnDelete();
            $table->foreignId('course_enrollment_id')->constrained('course_enrollments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('new_from_surah_id')->nullable()->constrained('surahs')->nullOnDelete();
            $table->unsignedSmallInteger('new_from_ayah')->nullable();
            $table->foreignId('new_to_surah_id')->nullable()->constrained('surahs')->nullOnDelete();
            $table->unsignedSmallInteger('new_to_ayah')->nullable();
            $table->string('new_result', 20)->nullable();
            $table->unsignedTinyInteger('new_score')->nullable();
            $table->text('recent_revision_text')->nullable();
            $table->string('recent_revision_result', 20)->nullable();
            $table->unsignedTinyInteger('recent_revision_score')->nullable();
            $table->text('old_revision_text')->nullable();
            $table->string('old_revision_result', 20)->nullable();
            $table->unsignedTinyInteger('old_revision_score')->nullable();
            $table->unsignedInteger('mistake_count')->default(0);
            $table->unsignedInteger('haraka_mistakes')->default(0);
            $table->unsignedInteger('word_mistakes')->default(0);
            $table->unsignedInteger('fluency_mistakes')->default(0);
            $table->text('teacher_note')->nullable();
            $table->text('parent_visible_note')->nullable();
            $table->text('supervisor_note')->nullable();
            $table->text('next_target')->nullable();
            $table->boolean('requires_parent_attention')->default(false);
            $table->boolean('requires_supervisor_review')->default(false);
            $table->string('overall_status', 20)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_offering_session_id', 'course_enrollment_id'], 'quran_session_records_session_enrollment_unique');
            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_session_records');
    }
};
