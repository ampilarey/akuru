<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2.2 — subject-agnostic question bank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('question_type', 32);
            $table->string('pattern', 32);
            $table->string('title')->nullable();
            $table->text('question_text');
            $table->text('secondary_text')->nullable();
            $table->text('explanation')->nullable();
            $table->json('options')->nullable();
            $table->json('correct_answer')->nullable();
            $table->json('acceptable_answers')->nullable();
            $table->json('normalization_settings')->nullable();
            $table->string('difficulty', 16)->default('medium');
            $table->string('skill_tag')->nullable();
            $table->json('attachments')->nullable();
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['subject_id', 'question_type']);
            $table->index(['course_id', 'pattern']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
