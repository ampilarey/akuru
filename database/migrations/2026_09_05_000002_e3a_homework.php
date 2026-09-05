<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E3a — homework that reaches the family.
 *
 * `lesson_logs.homework` has existed since 2025 and teachers already fill it in
 * the register; nothing has ever shown it to a student or parent. This adds the
 * two things missing: a due date, and a record of the pupil ticking it done.
 *
 * Additive only (rule 9). `homework_ticks` records something that happens in
 * time, so it carries `academic_year_id` (rule 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_logs', function (Blueprint $table) {
            $table->date('homework_due_date')->nullable()->after('homework');
        });

        Schema::create('homework_ticks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_log_id')->constrained('lesson_logs')->cascadeOnDelete();
            $table->unsignedBigInteger('student_id');
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->timestamp('ticked_at');
            $table->timestamps();

            // One tick per pupil per piece of homework: ticking twice is the
            // same fact, not two.
            $table->unique(['lesson_log_id', 'student_id'], 'homework_tick_unique');
            $table->index(['student_id', 'ticked_at']);
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_ticks');
        Schema::table('lesson_logs', function (Blueprint $table) {
            $table->dropColumn('homework_due_date');
        });
    }
};
