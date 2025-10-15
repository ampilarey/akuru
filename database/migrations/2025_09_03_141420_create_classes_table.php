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
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g., "Grade 1", "Quran Class A"
            $table->string('name_arabic')->nullable();
            $table->string('name_dhivehi')->nullable();
            $table->string('section')->nullable(); // e.g., "A", "B", "Morning", "Evening"
            $table->string('level'); // e.g., "Primary", "Secondary", "Quran", "Arabic"
            $table->integer('capacity')->default(30);
            $table->foreignId('class_teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
