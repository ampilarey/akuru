<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1B.1 — course offerings and delivery modes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->string('title');
            $table->string('title_dv')->nullable();
            $table->string('title_ar')->nullable();
            $table->string('slug');
            $table->string('delivery_mode', 32);
            $table->string('status', 20)->default('draft');
            $table->string('pin_mode', 20)->default('latest');
            $table->unsignedInteger('seat_limit')->nullable();
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedBigInteger('term_id')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['course_id', 'slug'], 'course_offering_course_slug_uq');
            $table->index(['course_id', 'delivery_mode', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};
