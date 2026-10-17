<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BLOG_PLAN BL4 (STATUS §5sk): readers follow a blog.
 *
 *  - `library_follows`: one reader following one writer, and whether they
 *    want new posts by email too (in the app always).
 *  - `writer_profiles.followers_count`: a cached count, kept by the follow
 *    action.
 *  - `library_items.readers_notified_at`: when readers were told of an item,
 *    so publishing it again (after an unpublish, or an approved edit) never
 *    tells them twice.
 *
 * Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('writer_id')->constrained('writer_profiles')->cascadeOnDelete();
            $table->boolean('notify_email')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'writer_id']);
        });

        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->unsignedInteger('followers_count')->default(0)->after('status');
        });

        Schema::table('library_items', function (Blueprint $table) {
            $table->timestamp('readers_notified_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn('readers_notified_at');
        });
        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->dropColumn('followers_count');
        });
        Schema::dropIfExists('library_follows');
    }
};
