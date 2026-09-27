<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The remaining shelf filters (LIBRARY_PLAN §8.2–§8.4, BACKLOG B5, STATUS
 * §5in).
 *
 * `difficulty` is the one filter the plan asked for that had no column:
 * beginner, intermediate or advanced, set by the writer or the office and
 * empty until somebody says. Reading time already existed; popularity by
 * period reads the reading events, which gain the index that question
 * needs (an item's events in a window); peer review and open access are
 * read off what the research already records. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->string('difficulty', 16)->nullable()->after('language');
        });
        Schema::table('library_reading_events', function (Blueprint $table) {
            $table->index(['library_item_id', 'occurred_at'], 'lre_item_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::table('library_reading_events', function (Blueprint $table) {
            $table->dropIndex('lre_item_occurred_idx');
        });
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn('difficulty');
        });
    }
};
