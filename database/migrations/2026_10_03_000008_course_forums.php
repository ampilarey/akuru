<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moodle parity slice M3 (STATUS §5oj): a discussion forum on every course.
 *
 * A topic and its replies. Both happen in time, so both carry the academic
 * year they were written in (rule 10). A moderator hides rather than deletes,
 * so a hidden post can be shown again and the thread keeps its shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_forum_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('replies_count')->default(0);
            $table->timestamp('last_post_at')->nullable();
            $table->timestamps();
            $table->index(['course_id', 'is_pinned', 'last_post_at'], 'forum_topics_course_order_idx');
        });

        Schema::create('course_forum_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('course_forum_topics')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['topic_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_forum_posts');
        Schema::dropIfExists('course_forum_topics');
    }
};
