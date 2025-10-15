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
        Schema::create('quran_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->string('surah_name'); // e.g., "Al-Fatiha", "Al-Baqarah"
            $table->string('surah_name_arabic'); // e.g., "الفاتحة", "البقرة"
            $table->integer('surah_number'); // 1-114
            $table->integer('from_ayah')->nullable(); // Starting ayah
            $table->integer('to_ayah')->nullable(); // Ending ayah
            $table->enum('type', ['memorization', 'recitation', 'revision']); // Type of progress
            $table->enum('status', ['in_progress', 'completed', 'needs_revision'])->default('in_progress');
            $table->integer('accuracy_percentage')->nullable(); // 0-100
            $table->text('teacher_notes')->nullable();
            $table->text('teacher_notes_arabic')->nullable();
            $table->date('date_completed')->nullable();
            $table->date('last_revision_date')->nullable();
            $table->integer('revision_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quran_progress');
    }
};
