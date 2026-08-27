<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5 parity slice P2 (ADR-025 gate item 2): SPEC §52.18 quran_hifz_assignments,
 * engine-keyed, richer than the legacy hifz_assignments it replaces (types,
 * due date, letter/haraka practice targets). Also the §52.19 link the F3
 * submissions table was missing: quran_recitation_submissions.
 * quran_hifz_assignment_id (additive, nullable). Letter/haraka FKs are
 * table-level only — no cross-component code reference. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_hifz_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('course_offering_id')->nullable()->constrained('course_offerings')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('surah_id')->nullable()->constrained('surahs')->nullOnDelete();
            $table->unsignedSmallInteger('start_ayah_number')->nullable();
            $table->unsignedSmallInteger('end_ayah_number')->nullable();
            $table->foreignId('expected_letter_id')->nullable()->constrained('arabic_letters')->nullOnDelete();
            $table->foreignId('expected_haraka_id')->nullable()->constrained('arabic_harakas')->nullOnDelete();
            $table->string('assignment_type', 40);
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('assigned');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['teacher_id', 'due_date']);
        });

        Schema::table('quran_recitation_submissions', function (Blueprint $table) {
            $table->foreignId('quran_hifz_assignment_id')
                ->nullable()
                ->after('course_enrollment_id')
                ->constrained('quran_hifz_assignments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quran_recitation_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quran_hifz_assignment_id');
        });
        Schema::dropIfExists('quran_hifz_assignments');
    }
};
