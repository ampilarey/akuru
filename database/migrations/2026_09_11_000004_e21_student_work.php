<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E21 — student work showcase.
 *
 * Photograph paper work, route it to the right parent. EduPage reads the
 * pupil's handwritten name with AI; the plan's judgement is that a v1 without
 * that is *"photo + pick the pupil, which is most of the value at a fraction
 * of the cost"* — and no AI in this phase anyway (rule 8).
 *
 * **The plan names the failure mode their own docs document: work sent to the
 * wrong parent.** So the schema is built around correcting that, not just
 * around storing a photo:
 *
 *  - `student_work_reassignments` records every time a photo moved from one
 *    pupil to another, with who did it and when. *"Which family saw my child's
 *    work, and for how long?"* is a question a school will be asked, and a
 *    silently-updated `student_id` cannot answer it.
 *  - the photo is a private media id, not a public path (rule 4, as E13c and
 *    E15 already do): a photograph of a child's schoolwork carries their
 *    handwriting and often their name.
 *
 * Carries `academic_year_id` (rule 10) — work happens in a term.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_work', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('photo_media_id')->constrained('media_files')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();

            $table->string('title')->nullable();
            $table->string('note')->nullable();
            $table->date('done_on');

            // Hidden work stays on the record. A teacher who realises a photo
            // caught another child's work in frame needs it gone from the
            // family view *now*, without losing the fact that it existed.
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['student_id', 'done_on']);
            $table->index(['academic_year_id', 'done_on']);
        });

        Schema::create('student_work_reassignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_work_id')->constrained('student_work')->cascadeOnDelete();
            $table->foreignId('from_student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('to_student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('moved_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('student_work_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_work_reassignments');
        Schema::dropIfExists('student_work');
    }
};
