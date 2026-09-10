<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E13a — a material a teacher can reuse.
 *
 * `lesson_logs.materials` is a free-text JSON array, typed as a comma-separated
 * string in the register. A teacher retypes "Textbook p.12, worksheet" every
 * lesson, nothing is searchable, and nothing can be attached to homework.
 *
 * The free-text column is **left exactly as it is** (rule 9): existing lesson
 * logs keep their strings and keep displaying them. This adds the structured
 * way alongside; migrating the old strings is a later slice's job and needs a
 * human to decide which strings are the same material.
 *
 * No `academic_year_id`: a material is standing content, not something that
 * happens in time (rule 10). The same worksheet is the same worksheet next year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index('created_by');
            $table->index('subject_id');
        });

        Schema::create('lesson_log_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_log_id')->constrained('lesson_logs')->cascadeOnDelete();
            $table->foreignId('teaching_material_id')->constrained('teaching_materials')->cascadeOnDelete();
            $table->timestamps();

            // Attaching the same material twice to one lesson is the same fact.
            $table->unique(['lesson_log_id', 'teaching_material_id'], 'lesson_material_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_log_material');
        Schema::dropIfExists('teaching_materials');
    }
};
