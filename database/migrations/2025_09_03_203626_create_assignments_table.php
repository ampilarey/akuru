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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->string('title');
            $table->string('title_arabic')->nullable();
            $table->string('title_dhivehi')->nullable();
            $table->text('description');
            $table->text('description_arabic')->nullable();
            $table->text('description_dhivehi')->nullable();
            $table->text('instructions');
            $table->text('instructions_arabic')->nullable();
            $table->text('instructions_dhivehi')->nullable();
            $table->date('due_date');
            $table->time('due_time')->default('23:59');
            $table->unsignedInteger('max_marks')->default(100);
            $table->enum('type', ['homework', 'project', 'quiz', 'exam', 'presentation'])->default('homework');
            $table->enum('status', ['draft', 'published', 'closed'])->default('draft');
            $table->json('attachments')->nullable(); // Store file paths
            $table->boolean('allow_late_submission')->default(false);
            $table->unsignedInteger('late_penalty_percentage')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};