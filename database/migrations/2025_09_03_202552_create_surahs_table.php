<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('surahs', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('index')->unique(); // 1-114
            $table->string('arabic_name');
            $table->string('english_name');
            $table->string('transliteration')->nullable();
            $table->unsignedSmallInteger('ayah_count');
            $table->enum('revelation_place', ['Meccan', 'Medinan']);
            $table->unsignedTinyInteger('juz_start')->nullable(); // Which Juz it starts in
            $table->unsignedTinyInteger('juz_end')->nullable(); // Which Juz it ends in
            $table->text('description')->nullable();
            $table->text('description_arabic')->nullable();
            $table->text('description_dhivehi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surahs');
    }
};