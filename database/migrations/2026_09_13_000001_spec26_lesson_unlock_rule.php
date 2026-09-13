<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC §26: "Unlock rules should be stored in JSON settings at **course,
 * module, lesson, or offering level**." Only the course level existed
 * (`courses.unlock_rules`, added by the §26 slice).
 *
 * §13 lists **"Unlock rule"** among a lesson's own fields, and §19's
 * `settings.lock_next_lesson` is the same idea expressed badly — written by
 * two Actions and read by nothing. Three sections were pointing at this one
 * missing column.
 *
 * Null means "inherit the course's rule", which is what every existing lesson
 * does today, so nothing changes for a row that says nothing (rule 9:
 * additive, no backfill).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->json('unlock_rule')->nullable()->after('completion_rule');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('unlock_rule');
        });
    }
};
