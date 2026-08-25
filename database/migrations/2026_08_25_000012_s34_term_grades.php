<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S3.4 — computed term_grades cache, competencies, competency assessments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('term_id')->constrained('terms')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->decimal('weighted_percent', 5, 2)->nullable();
            $table->string('grade')->nullable();
            $table->decimal('grade_point', 3, 2)->nullable();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->json('components');
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'subject_id', 'term_id'], 'term_grades_student_subject_term_uidx');
            $table->index(['class_id', 'subject_id', 'term_id'], 'term_grades_class_subject_term_idx');
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
            $table->foreign('class_id')->references('id')->on('classes')->restrictOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
        });

        Schema::create('competencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subject_id');
            $table->string('name');
            $table->string('name_arabic')->nullable();
            $table->string('name_dhivehi')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->index(['subject_id', 'sort_order'], 'competencies_subject_sort_idx');
        });

        Schema::create('competency_assessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->restrictOnDelete();
            $table->string('level');
            $table->unsignedBigInteger('assessed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'competency_id', 'term_id'], 'comp_assess_student_comp_term_uidx');
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
        });

        $now = now();
        DB::table('settings')->insertOrIgnore([
            'key' => 'exams_compute_rank',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'exams',
            'label' => 'Compute class rank on term grades (ties share rank)',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_assessments');
        Schema::dropIfExists('competencies');
        Schema::dropIfExists('term_grades');
    }
};
