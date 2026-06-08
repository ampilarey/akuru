<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hifz_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('dean_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('default_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->enum('status', ['active', 'inactive', 'completed'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('hifz_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hifz_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date');
            $table->date('target_completion_date')->nullable();
            $table->foreignId('current_surah_id')->nullable()->constrained('surahs')->nullOnDelete();
            $table->unsignedTinyInteger('current_juz')->nullable();
            $table->unsignedSmallInteger('current_page')->nullable();
            $table->enum('status', ['active', 'paused', 'completed', 'transferred'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['hifz_program_id', 'student_id']);
            $table->index(['teacher_id', 'status']);
        });

        Schema::create('hifz_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hifz_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('assignment_date');
            $table->foreignId('new_from_surah_id')->nullable()->constrained('surahs')->nullOnDelete();
            $table->unsignedSmallInteger('new_from_ayah')->nullable();
            $table->foreignId('new_to_surah_id')->nullable()->constrained('surahs')->nullOnDelete();
            $table->unsignedSmallInteger('new_to_ayah')->nullable();
            $table->text('recent_revision_text')->nullable();
            $table->text('old_revision_text')->nullable();
            $table->text('homework_note')->nullable();
            $table->enum('status', ['assigned', 'completed', 'missed', 'cancelled'])->default('assigned');
            $table->timestamps();

            $table->index(['hifz_program_id', 'assignment_date']);
        });

        Schema::create('hifz_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hifz_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('session_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('title')->nullable();
            $table->text('general_note')->nullable();
            $table->text('supervisor_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->enum('status', ['draft', 'completed', 'reviewed', 'locked'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hifz_program_id', 'session_date']);
            $table->index(['teacher_id', 'session_date']);
        });

        Schema::create('hifz_session_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hifz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hifz_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('attendance_status', ['present', 'late', 'absent', 'excused'])->default('present');
            $table->foreignId('new_from_surah_id')->nullable()->constrained('surahs')->nullOnDelete();
            $table->unsignedSmallInteger('new_from_ayah')->nullable();
            $table->foreignId('new_to_surah_id')->nullable()->constrained('surahs')->nullOnDelete();
            $table->unsignedSmallInteger('new_to_ayah')->nullable();
            $table->enum('new_result', ['pass', 'pass_with_notes', 'repeat', 'not_prepared', 'not_done'])->nullable();
            $table->unsignedTinyInteger('new_score')->nullable();
            $table->text('recent_revision_text')->nullable();
            $table->enum('recent_revision_result', ['pass', 'repeat', 'not_done'])->nullable();
            $table->unsignedTinyInteger('recent_revision_score')->nullable();
            $table->text('old_revision_text')->nullable();
            $table->enum('old_revision_result', ['pass', 'repeat', 'not_done'])->nullable();
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
            $table->enum('overall_status', ['excellent', 'good', 'needs_revision', 'weak', 'absent'])->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hifz_session_id', 'student_id']);
            $table->index(['student_id', 'created_at']);
        });

        Schema::create('hifz_mistakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hifz_session_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('quran_page_id')->nullable();
            $table->unsignedBigInteger('quran_ayah_id')->nullable();
            $table->unsignedBigInteger('quran_word_id')->nullable();
            $table->unsignedTinyInteger('surah_number')->nullable();
            $table->unsignedSmallInteger('ayah_number')->nullable();
            $table->unsignedSmallInteger('word_number')->nullable();
            $table->enum('mistake_type', [
                'haraka', 'wrong_letter', 'wrong_word', 'skipped_word', 'extra_word',
                'wrong_ayah', 'wrong_order', 'hesitation', 'weak_fluency', 'waqf_ibtida', 'tajweed', 'other',
            ]);
            $table->enum('severity', ['minor', 'medium', 'major'])->default('minor');
            $table->text('teacher_note')->nullable();
            $table->text('supervisor_note')->nullable();
            $table->boolean('parent_visible')->default(false);
            $table->timestamps();

            $table->index(['hifz_session_record_id']);
            $table->index(['student_id', 'mistake_type']);
        });

        Schema::create('hifz_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hifz_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['surah_completed', 'juz_completed', 'page_completed', 'quran_completed', 'custom']);
            $table->unsignedTinyInteger('surah_number')->nullable();
            $table->unsignedTinyInteger('juz_number')->nullable();
            $table->unsignedSmallInteger('page_number')->nullable();
            $table->string('title')->nullable();
            $table->timestamp('completed_at');
            $table->foreignId('recommended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recommended_at')->nullable();
            $table->foreignId('supervisor_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['pending', 'supervisor_reviewed', 'approved', 'rejected'])->default('pending');
            $table->boolean('certificate_issued')->default(false);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hifz_program_id', 'status']);
            $table->index(['student_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hifz_milestones');
        Schema::dropIfExists('hifz_mistakes');
        Schema::dropIfExists('hifz_session_records');
        Schema::dropIfExists('hifz_sessions');
        Schema::dropIfExists('hifz_assignments');
        Schema::dropIfExists('hifz_enrollments');
        Schema::dropIfExists('hifz_programs');
    }
};
