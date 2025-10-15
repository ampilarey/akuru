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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->string('assignment_name');
            $table->string('assignment_name_arabic')->nullable();
            $table->string('assignment_name_dhivehi')->nullable();
            $table->enum('type', ['homework', 'quiz', 'test', 'exam', 'project', 'participation', 'quran_recitation', 'quran_memorization']);
            $table->decimal('score', 5, 2); // e.g., 85.50
            $table->decimal('max_score', 5, 2); // e.g., 100.00
            $table->decimal('percentage', 5, 2)->nullable(); // Calculated percentage
            $table->string('letter_grade')->nullable(); // A+, A, B+, etc.
            $table->text('comments')->nullable();
            $table->text('comments_arabic')->nullable();
            $table->text('comments_dhivehi')->nullable();
            $table->date('date_given');
            $table->date('due_date')->nullable();
            $table->boolean('is_final')->default(false); // Is this a final grade for the term?
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
