<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qur’an A.3 — offering/halaqa mapping. Integer Hifz ids, no FK, no dual-write.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offering_halaqa_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->unsignedBigInteger('hifz_program_id');
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->timestamps();

            $table->unique('course_offering_id');
            $table->index('hifz_program_id');
        });

        Schema::create('offering_halaqa_session_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_session_id')->constrained('course_offering_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('hifz_session_id');
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->timestamps();

            $table->unique('course_offering_session_id');
            $table->index('hifz_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offering_halaqa_session_links');
        Schema::dropIfExists('offering_halaqa_links');
    }
};
