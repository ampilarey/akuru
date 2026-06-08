<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_mushafs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('source_type', ['pdf', 'word', 'manual', 'imported'])->default('manual');
            $table->string('source_file_path')->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('locked')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quran_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quran_mushaf_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('page_number');
            $table->unsignedTinyInteger('juz_number')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('pdf_page_number')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->unique(['quran_mushaf_id', 'page_number']);
        });

        Schema::create('quran_ayahs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quran_mushaf_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quran_page_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('surah_number');
            $table->unsignedSmallInteger('ayah_number');
            $table->unsignedTinyInteger('juz_number')->nullable();
            $table->unsignedSmallInteger('page_number')->nullable();
            $table->longText('text_uthmani');
            $table->longText('text_simple')->nullable();
            $table->timestamps();

            $table->unique(['quran_mushaf_id', 'surah_number', 'ayah_number'], 'quran_ayahs_mushaf_surah_ayah_unique');
            $table->index(['quran_mushaf_id', 'page_number']);
        });

        Schema::create('quran_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quran_mushaf_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quran_ayah_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('surah_number');
            $table->unsignedSmallInteger('ayah_number');
            $table->unsignedSmallInteger('word_number');
            $table->string('word_text');
            $table->string('word_text_simple')->nullable();
            $table->unsignedSmallInteger('page_number')->nullable();
            $table->timestamps();

            $table->unique(['quran_mushaf_id', 'surah_number', 'ayah_number', 'word_number'], 'quran_words_mushaf_pos_unique');
        });

        Schema::create('quran_word_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quran_mushaf_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quran_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quran_word_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('page_number');
            $table->decimal('x', 8, 4);
            $table->decimal('y', 8, 4);
            $table->decimal('width', 8, 4);
            $table->decimal('height', 8, 4);
            $table->enum('coordinate_type', ['percentage', 'pixel'])->default('percentage');
            $table->timestamps();

            $table->index(['quran_page_id', 'quran_word_id']);
        });

        Schema::table('hifz_mistakes', function (Blueprint $table) {
            $table->foreign('quran_page_id')->references('id')->on('quran_pages')->nullOnDelete();
            $table->foreign('quran_ayah_id')->references('id')->on('quran_ayahs')->nullOnDelete();
            $table->foreign('quran_word_id')->references('id')->on('quran_words')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hifz_mistakes', function (Blueprint $table) {
            $table->dropForeign(['quran_page_id']);
            $table->dropForeign(['quran_ayah_id']);
            $table->dropForeign(['quran_word_id']);
        });

        Schema::dropIfExists('quran_word_positions');
        Schema::dropIfExists('quran_words');
        Schema::dropIfExists('quran_ayahs');
        Schema::dropIfExists('quran_pages');
        Schema::dropIfExists('quran_mushafs');
    }
};
