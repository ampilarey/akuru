<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RESEARCH_ARTICLES_PLAN R2: the website's research moves into the Digital
 * Library. `imported_post_id` records which `posts` row an item came from,
 * so the import can run again without duplicating and an old
 * `/research/{slug}` address can find its new home. Additive only (rule 9):
 * the `posts` rows stay where they are.
 *
 * No foreign key: `posts` belongs to the Website domain, and a later cleanup
 * slice may retire that table once production has run on the library.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->unsignedBigInteger('imported_post_id')->nullable()->unique()->after('writer_id');
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropUnique(['imported_post_id']);
            $table->dropColumn('imported_post_id');
        });
    }
};
