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
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->integer('attempt_number')->default(1);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->decimal('score', 6, 2)->nullable(); // Final score as percentage
            $table->integer('points_earned')->nullable();
            $table->integer('total_points')->nullable();
            $table->json('answers'); // Student's answers
            $table->integer('time_spent_seconds')->nullable();
            $table->enum('status', ['in_progress', 'completed', 'submitted', 'graded'])->default('in_progress');
            $table->text('feedback')->nullable(); // Teacher feedback
            $table->timestamps();
            
            $table->index(['quiz_id', 'student_id']);
            $table->index(['student_id', 'status']);
            $table->unique(['quiz_id', 'student_id', 'attempt_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};