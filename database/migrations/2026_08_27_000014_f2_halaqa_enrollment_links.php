<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase F2 — Hifz enrollment ↔ engine enrollment mapping. Same shape rules as
 * the A.3 halaqa link tables: integer Hifz ids, no FK into Hifz, additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offering_halaqa_enrollment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_enrollment_id')->constrained('course_enrollments')->cascadeOnDelete();
            $table->unsignedBigInteger('hifz_enrollment_id');
            $table->timestamps();

            $table->unique('hifz_enrollment_id');
            $table->index('course_enrollment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offering_halaqa_enrollment_links');
    }
};
