<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L8 extras — the author page's featured works and links (LIBRARY_PLAN
 * §8.7, BACKLOG B6, STATUS §5im).
 *
 * A writer may pin up to three of their own published works at the top of
 * their page, and give readers a website and social addresses. Both are
 * the writer's own words about themselves, so they sit on the profile as
 * JSON rather than as tables of their own: nothing else reads them.
 * Additive; a profile without them shows as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->json('featured_item_ids')->nullable()->after('photo_media_file_id');
            $table->json('social_links')->nullable()->after('featured_item_ids');
        });
    }

    public function down(): void
    {
        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->dropColumn(['featured_item_ids', 'social_links']);
        });
    }
};
