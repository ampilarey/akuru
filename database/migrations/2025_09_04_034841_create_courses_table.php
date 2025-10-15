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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_category_id')->constrained();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_desc');
            $table->longText('body');
            $table->string('cover_image');
            $table->enum('language', ['en', 'ar', 'dv', 'mixed'])->default('en');
            $table->enum('level', ['kids', 'youth', 'adult', 'all'])->default('all');
            $table->json('schedule')->nullable();
            $table->decimal('fee', 10, 2)->nullable();
            $table->enum('status', ['open', 'closed', 'upcoming'])->default('open');
            $table->integer('seats')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            
            $table->index(['course_category_id', 'status']);
            $table->index(['status', 'language']);
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
