<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S5.5 — appraisal cycles, observations, and CPD records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisal_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->date('opens_at');
            $table->date('closes_at');
            $table->json('template')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });

        Schema::create('appraisals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('appraisal_cycles')->cascadeOnDelete();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->foreignId('appraiser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('ratings')->nullable();
            $table->text('strengths')->nullable();
            $table->text('development_areas')->nullable();
            $table->json('goals')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('staff_comment')->nullable();
            $table->timestamps();

            $table->unique(['cycle_id', 'staff_profile_id'], 'appraisal_cycle_staff_uq');
        });

        Schema::create('lesson_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->foreignId('observer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('criteria')->nullable();
            $table->text('summary')->nullable();
            $table->boolean('shared_with_staff')->default(false);
            $table->timestamps();

            $table->index(['staff_profile_id', 'date'], 'lesson_obs_staff_date_idx');
        });

        Schema::create('cpd_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->string('title');
            $table->string('provider')->nullable();
            $table->decimal('hours', 5, 1)->default(0);
            $table->date('date')->nullable();
            $table->foreignId('certificate_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->index(['staff_profile_id', 'date'], 'cpd_staff_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpd_records');
        Schema::dropIfExists('lesson_observations');
        Schema::dropIfExists('appraisals');
        Schema::dropIfExists('appraisal_cycles');
    }
};
