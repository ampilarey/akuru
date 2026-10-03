<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moodle parity slice M2 (STATUS §5oi): rubrics for teacher-marked work.
 *
 * A rubric belongs to a course and holds its criteria, each with scored
 * levels, as JSON (the criteria are edited and read whole, never queried one
 * by one). An activity or assessment may name one. A marked attempt keeps a
 * snapshot of the levels chosen, so a rubric edited later never changes a mark
 * already given. All additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('criteria');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('rubric_id')->nullable()->after('passing_score')->constrained('rubrics')->nullOnDelete();
        });
        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('rubric_id')->nullable()->after('requires_teacher_marking')->constrained('rubrics')->nullOnDelete();
        });
        Schema::table('activity_attempts', function (Blueprint $table) {
            $table->json('rubric_scores')->nullable()->after('feedback');
        });
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->json('rubric_scores')->nullable()->after('item_scores');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_attempts', fn (Blueprint $table) => $table->dropColumn('rubric_scores'));
        Schema::table('activity_attempts', fn (Blueprint $table) => $table->dropColumn('rubric_scores'));
        Schema::table('assessments', fn (Blueprint $table) => $table->dropConstrainedForeignId('rubric_id'));
        Schema::table('activities', fn (Blueprint $table) => $table->dropConstrainedForeignId('rubric_id'));
        Schema::dropIfExists('rubrics');
    }
};
