<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RESEARCH_ARTICLES_PLAN R3: peer review is a must, and a revision is a new
 * round. Additive only (rule 9).
 *
 * - `library_items.review_round`: the round a research item is in. It
 *   starts at 1 and goes up each time the writer resubmits after changes
 *   were asked for.
 * - `library_review_assignments.round`: the round an assignment was opened
 *   (or re-opened) in. An accept counts only in its own round: a reviewer
 *   who accepted one text has not accepted the revised one.
 *
 * Every existing row is round 1, which is what it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('review_round')->default(1)->after('status');
        });
        Schema::table('library_review_assignments', function (Blueprint $table) {
            $table->unsignedSmallInteger('round')->default(1)->after('recommendation');
        });
    }

    public function down(): void
    {
        Schema::table('library_review_assignments', function (Blueprint $table) {
            $table->dropColumn('round');
        });
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn('review_round');
        });
    }
};
