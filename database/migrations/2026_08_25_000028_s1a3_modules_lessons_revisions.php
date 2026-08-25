<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1A.3 — modules, lessons, draft content_blocks, immutable lesson_revisions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->string('title');
            $table->string('title_dv')->nullable();
            $table->string('title_ar')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('course_module_id')->constrained('course_modules')->restrictOnDelete();
            $table->string('title');
            $table->string('title_dv')->nullable();
            $table->string('title_ar')->nullable();
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->boolean('is_preview')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['course_id', 'slug'], 'lesson_course_slug_uq');
        });

        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('course_module_id')->constrained('course_modules')->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->restrictOnDelete();
            $table->string('type', 40);
            $table->unsignedInteger('position')->default(0);
            $table->string('title')->nullable();
            $table->json('data');
            $table->json('settings')->nullable();
            $table->boolean('is_required')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lesson_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->json('snapshot_json');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['lesson_id', 'revision_number'], 'lesson_revision_number_uq');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreign('current_revision_id')->references('id')->on('lesson_revisions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropForeign(['current_revision_id']);
        });
        Schema::dropIfExists('lesson_revisions');
        Schema::dropIfExists('content_blocks');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('course_modules');
    }
};
