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
        Schema::create('recitation_practices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('surah_id')->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('ayah_from');
            $table->unsignedSmallInteger('ayah_to');
            $table->string('audio_path')->nullable();
            $table->text('tajweed_notes')->nullable();
            $table->text('tajweed_notes_arabic')->nullable();
            $table->text('tajweed_notes_dhivehi')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('evaluated_at')->nullable();
            $table->enum('status', ['pending', 'evaluated', 'approved', 'needs_revision'])->default('pending');
            $table->unsignedTinyInteger('accuracy_score')->nullable(); // 0-100
            $table->unsignedTinyInteger('tajweed_score')->nullable(); // 0-100
            $table->unsignedTinyInteger('fluency_score')->nullable(); // 0-100
            $table->text('teacher_feedback')->nullable();
            $table->text('teacher_feedback_arabic')->nullable();
            $table->text('teacher_feedback_dhivehi')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recitation_practices');
    }
};