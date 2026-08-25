<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S2.7 — class_attendance. Unique student+date+period; null period = daily
 * via generated period_key (MySQL unique treats NULLs as distinct).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->date('date');
            $table->foreignId('period_id')->nullable()->constrained('periods')->nullOnDelete();
            $table->unsignedBigInteger('period_key')->default(0);
            $table->foreignId('lesson_log_id')->nullable()->constrained('lesson_logs')->nullOnDelete();
            $table->string('status', 20);
            $table->unsignedSmallInteger('minutes_late')->nullable();
            $table->string('source', 20)->default('register');
            $table->unsignedBigInteger('marked_by');
            $table->foreignId('absence_note_id')->nullable()->constrained('absence_notes')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'date', 'period_key'], 'class_attendance_student_day_period');
            $table->index(['class_id', 'date']);
            $table->index(['academic_year_id', 'date', 'status']);
            $table->index(['lesson_log_id']);
        });

        $now = now();
        $settings = [
            [
                'key' => 'attendance_mode',
                'value' => 'per_lesson',
                'type' => 'string',
                'group' => 'academics',
                'label' => 'Attendance mode (per_lesson or daily)',
            ],
            [
                'key' => 'attendance_notify',
                'value' => 'absent_only',
                'type' => 'string',
                'group' => 'academics',
                'label' => 'SMS parents on absent_only or absent_and_late',
            ],
            [
                'key' => 'attendance_chronic_threshold',
                'value' => '5',
                'type' => 'string',
                'group' => 'academics',
                'label' => 'Unexcused absent days before chronic list',
            ],
        ];

        foreach ($settings as $row) {
            DB::table('settings')->insertOrIgnore(array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_attendance');
    }
};
