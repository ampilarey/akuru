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
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->text('content')->nullable(); // Text submission
            $table->json('attachments')->nullable(); // File attachments
            $table->timestamp('submitted_at');
            $table->enum('status', ['submitted', 'graded', 'returned'])->default('submitted');
            $table->unsignedInteger('marks_obtained')->nullable();
            $table->text('teacher_feedback')->nullable();
            $table->text('teacher_feedback_arabic')->nullable();
            $table->text('teacher_feedback_dhivehi')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('graded_at')->nullable();
            $table->boolean('is_late')->default(false);
            $table->timestamps();
            
            // Ensure one submission per student per assignment
            $table->unique(['assignment_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};