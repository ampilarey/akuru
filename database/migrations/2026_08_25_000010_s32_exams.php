<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S3.2 — exams scheduling, calendar-aware checks, status flow.
 * No student-keyed exam_marks yet (S3.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('term_id')->constrained('terms')->restrictOnDelete();
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('exam_type_id')->constrained('exam_types')->restrictOnDelete();
            $table->string('name');
            $table->date('exam_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->decimal('max_marks', 8, 2)->default(100);
            $table->unsignedSmallInteger('weight_override')->nullable();
            $table->text('instructions')->nullable();
            $table->string('status', 24)->default('scheduled');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('class_id')->references('id')->on('classes')->restrictOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
            $table->foreign('room_id')->references('id')->on('rooms')->nullOnDelete();
            $table->index(['academic_year_id', 'class_id', 'exam_date'], 'exams_year_class_date_idx');
            $table->index(['status', 'exam_date'], 'exams_status_date_idx');
        });

        Schema::create('exam_status_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->string('from_status', 24);
            $table->string('to_status', 24);
            $table->unsignedBigInteger('actor_id');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['exam_id', 'created_at'], 'exam_audits_exam_created_idx');
        });

        $now = now();
        DB::table('settings')->insertOrIgnore([
            'key' => 'exams_max_per_class_per_day',
            'value' => '1',
            'type' => 'string',
            'group' => 'exams',
            'label' => 'Max exams per class per day before same-day warning',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_status_audits');
        Schema::dropIfExists('exams');
    }
};
