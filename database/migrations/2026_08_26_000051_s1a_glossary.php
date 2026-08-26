<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1A glossary / term bank (SPEC §22 / §43). Catalog, not time-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glossary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->nullable()->constrained('course_subjects')->nullOnDelete();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('term');
            $table->string('term_dv')->nullable();
            $table->string('term_ar')->nullable();
            $table->string('transliteration')->nullable();
            $table->text('meaning_primary')->nullable();
            $table->text('meaning_secondary')->nullable();
            $table->text('meaning_dv')->nullable();
            $table->text('meaning_ar')->nullable();
            $table->text('description')->nullable();
            $table->text('description_dv')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('example_text')->nullable();
            $table->text('example_translation')->nullable();
            $table->text('example_text_dv')->nullable();
            $table->text('example_text_ar')->nullable();
            $table->json('tags')->nullable();
            $table->foreignId('level_id')->nullable()->constrained('course_levels')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('audio_media_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->foreignId('image_media_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->foreignId('example_audio_media_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->foreignId('diagram_media_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->timestamps();

            $table->index(['subject_id', 'term']);
            $table->index('category_id');
        });

        Schema::create('lesson_glossary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('glossary_item_id')->constrained('glossary_items')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['lesson_id', 'glossary_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_glossary_items');
        Schema::dropIfExists('glossary_items');
    }
};
