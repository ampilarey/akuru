<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RESEARCH_ARTICLES_PLAN R3b: the reviewer's side of peer review. Additive
 * only (rule 9).
 *
 * - `due_at`: when the report is due (14 days from assignment unless the
 *   office sets another date; a new round sets a new date).
 * - `reminded_at`: the last reminder sent, so each reminder goes once.
 * - `coi_declared_at`: when the reviewer confirmed they have no conflict of
 *   interest. The item's text is not shown before it (§11.5's declaration
 *   pattern, on the reviewer's side).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_review_assignments', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->after('round');
            $table->timestamp('reminded_at')->nullable()->after('due_at');
            $table->timestamp('coi_declared_at')->nullable()->after('reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('library_review_assignments', function (Blueprint $table) {
            $table->dropColumn(['due_at', 'reminded_at', 'coi_declared_at']);
        });
    }
};
