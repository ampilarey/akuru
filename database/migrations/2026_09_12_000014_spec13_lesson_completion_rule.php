<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC §13 lists **"Completion rule"** among a lesson's fields, and its Lesson
 * Management list requires that a course creator can **"Set completion
 * rules"**. §27 opens with "Admin must be able to configure completion rules"
 * and gives five for a lesson:
 *
 *   > - Student clicks complete
 *   > - Required activities completed
 *   > - Quiz passed
 *   > - Assignment submitted
 *   > - Teacher approval received
 *
 * There was no column, and no rule of any kind. `StartOrCompleteLessonProgress
 * Action` took the status straight from the controller, so
 * `POST /learn/lessons/{id}/complete` recorded `completed` unconditionally.
 *
 * The consequence is not cosmetic. A student could open a lesson holding a
 * required activity, never attempt it, click "Mark complete", and the lesson
 * counted — which feeds `CalculateCourseProgressAction`, which feeds the
 * enrolment's `progress_percentage`, which is what §39's
 * `min_progress_percent` certificate rule is measured against. The one clause
 * in §27 that was implemented ("student clicks complete") was also the only
 * one that could ever be true.
 *
 * Stored as json rather than a string so a rule that needs configuration can
 * grow one without a second migration, matching `courses.unlock_rules` from
 * the §26 slice. Null means the default — clicking complete — so every
 * existing lesson keeps exactly the behaviour it has (rule 9: additive, no
 * backfill, nothing changes for a row that says nothing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->json('completion_rule')->nullable()->after('is_preview');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('completion_rule');
        });
    }
};
