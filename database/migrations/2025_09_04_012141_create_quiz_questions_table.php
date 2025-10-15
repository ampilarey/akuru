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
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
            $table->smallInteger('order')->default(1);
            $table->enum('type', ['mcq', 'truefalse', 'short', 'essay', 'matching']);
            $table->text('body'); // Question text
            $table->text('explanation')->nullable(); // Explanation for correct answer
            $table->json('options')->nullable(); // For MCQ, True/False, Matching
            $table->json('answer')->nullable(); // Correct answer(s)
            $table->smallInteger('points')->default(1);
            $table->string('image_path')->nullable(); // Optional question image
            $table->timestamps();
            
            $table->index(['quiz_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};