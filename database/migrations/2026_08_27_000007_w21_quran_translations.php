<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quran_translations')) {
            return;
        }

        Schema::create('quran_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quran_ayah_id')->constrained('quran_ayahs')->restrictOnDelete();
            $table->string('language', 16);
            $table->text('text');
            $table->string('source_name');
            $table->string('source_note')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['quran_ayah_id', 'language', 'source_name'], 'quran_translations_ayah_lang_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_translations');
    }
};
