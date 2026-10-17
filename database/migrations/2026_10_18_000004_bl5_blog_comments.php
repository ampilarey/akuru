<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BLOG_PLAN BL5 (STATUS §5sl): comments on blog posts, every one checked by
 * the office before it shows.
 *
 *  - `library_comments`: a signed-in reader's comment on a post, or the
 *    blog owner's reply to one (`parent_id`); plain text; its status —
 *    `pending`, `published`, `refused`, `hidden`, or `reported` (a reader
 *    reported it, so it is off the page until the office decides); who
 *    decided, when, and the note.
 *  - `writer_profiles.comments_enabled`: the writer's switch. An under-13's
 *    blog takes no comments whatever it says (D13).
 *
 * Additive (rule 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_item_id')->constrained('library_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('library_comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('report_count')->default(0);
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();

            $table->index(['library_item_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->boolean('comments_enabled')->default(true)->after('followers_count');
        });
    }

    public function down(): void
    {
        Schema::table('writer_profiles', function (Blueprint $table) {
            $table->dropColumn('comments_enabled');
        });
        Schema::dropIfExists('library_comments');
    }
};
