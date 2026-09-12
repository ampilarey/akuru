<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC §26 "Unlock Rules":
 *
 *   > Admin must be able to configure unlock rules.
 *   >
 *   > Unlock rules should be stored in JSON settings at course, module,
 *   > lesson, or offering level.
 *
 * Nothing was configurable. `EvaluateLessonUnlockAction` implemented exactly
 * one of §26's eleven rules — "Complete previous lesson first" — and applied it
 * unconditionally to every course in the system. **"All lessons open", the
 * first rule §26 lists, was unreachable**, so a reference course, a resource
 * library, or any course whose lessons are genuinely independent could not be
 * built: a student had to walk the whole sequence to reach the last page.
 *
 * A dedicated column rather than the existing `courses.meta` grab-bag: unlock
 * behaviour decides whether a student can open a page, and a setting with that
 * much consequence should be findable by name.
 *
 * Additive, nullable, and NULL keeps today's sequential behaviour (rule 9) —
 * no existing course changes on deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->json('unlock_rules')->nullable()->after('meta');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('unlock_rules');
        });
    }
};
