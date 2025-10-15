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
        Schema::create('lesson_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('classroom_id')->constrained('classes')->onDelete('cascade');
            $table->date('date');
            $table->foreignId('period_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('plan_topic_id')->nullable()->constrained()->onDelete('set null');
            $table->text('taught_summary');
            $table->text('homework')->nullable();
            $table->json('materials')->nullable(); // Materials used in class
            $table->smallInteger('present_count')->nullable();
            $table->smallInteger('late_count')->nullable();
            $table->smallInteger('absent_count')->nullable();
            $table->text('notes')->nullable();
            $table->enum('lesson_quality', ['excellent', 'good', 'satisfactory', 'needs_improvement'])->nullable();
            $table->timestamps();
            
            $table->index(['teacher_id', 'date']);
            $table->index(['classroom_id', 'date']);
            $table->unique(['teacher_id', 'subject_id', 'classroom_id', 'date', 'period_id'], 'lesson_unique_entry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_logs');
    }
};