<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BLOG_PLAN BL2a (STATUS §5sf): blog posts, written and checked.
 *
 *  - `library_post_series`: a writer's series of posts, in order.
 *  - `library_items` gains the post's series and place in it, *pin to my
 *    page*, and where its checking stands: `review_stage` is `teacher`
 *    while the class teacher has it, `office` once it reaches the office;
 *    `teacher_user_id` is the teacher who checks it.
 *
 * A post is a `library_items` row with `content_type = post`, so the review
 * trail, the publisher and the reader are the items'. Additive only (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_post_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('writer_id')->constrained('writer_profiles')->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('slug', 140);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['writer_id', 'slug']);
        });

        Schema::table('library_items', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->after('writer_id')->constrained('library_post_series')->nullOnDelete();
            $table->unsignedSmallInteger('series_position')->nullable()->after('series_id');
            $table->boolean('pinned')->default(false)->after('series_position');
            $table->string('review_stage', 20)->nullable()->after('status')->index();
            $table->foreignId('teacher_user_id')->nullable()->after('review_stage')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_user_id');
            $table->dropIndex(['review_stage']);
            $table->dropColumn('review_stage');
            $table->dropColumn('pinned');
            $table->dropColumn('series_position');
            $table->dropConstrainedForeignId('series_id');
        });
        Schema::dropIfExists('library_post_series');
    }
};
