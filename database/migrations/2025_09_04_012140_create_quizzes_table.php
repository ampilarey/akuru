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
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('classroom_id')->nullable()->constrained('classes')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->integer('time_limit_min')->nullable(); // Time limit in minutes
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('max_attempts')->default(1);
            $table->decimal('passing_score', 5, 2)->nullable(); // Percentage needed to pass
            $table->boolean('show_results')->default(true);
            $table->boolean('shuffle_questions')->default(false);
            $table->enum('status', ['draft', 'published', 'closed', 'archived'])->default('draft');
            $table->json('settings')->nullable(); // Additional quiz settings
            $table->timestamps();
            
            $table->index(['teacher_id', 'status']);
            $table->index(['subject_id', 'classroom_id']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};