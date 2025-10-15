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
        Schema::create('course_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('classroom_id')->constrained('classes')->onDelete('cascade');
            $table->string('academic_year')->default('2024-2025');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('objectives')->nullable(); // Learning objectives
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->timestamps();
            
            $table->index(['teacher_id', 'academic_year']);
            $table->index(['subject_id', 'classroom_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_plans');
    }
};