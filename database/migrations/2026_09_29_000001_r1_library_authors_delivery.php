<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RESEARCH_ARTICLES_PLAN R1: the library can hold the institute's research.
 * Additive only (rule 9).
 *
 * - `library_item_authors.instructor_profile_id`: an author who is one of the
 *   institute's teachers, linked to their public profile. The name column
 *   stays filled as well, so a shelf, a CSV or a citation never needs HR.
 * - `library_items.delivery`: how readers get it (D1) — reader, download or
 *   both. Backfilled by the plan's default: open-access research and
 *   articles read and download; everything else reads only.
 * - `library_reading_events.kind`: a page opened, or the file downloaded.
 *   Every existing row was a page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_item_authors', function (Blueprint $table) {
            $table->foreignId('instructor_profile_id')->nullable()->after('user_id')
                ->constrained('instructors')->nullOnDelete();
        });

        Schema::table('library_items', function (Blueprint $table) {
            $table->string('delivery', 16)->default('reader')->after('access_type');
        });

        DB::table('library_items')
            ->whereIn('content_type', ['research', 'article'])
            ->whereIn('access_type', ['free_public', 'free_login'])
            ->update(['delivery' => 'both']);

        Schema::table('library_reading_events', function (Blueprint $table) {
            $table->string('kind', 16)->default('page')->after('page_number');
        });
    }

    public function down(): void
    {
        Schema::table('library_reading_events', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn('delivery');
        });
        Schema::table('library_item_authors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instructor_profile_id');
        });
    }
};
