<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S3.7 — awards, student awards, and generated document types.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_arabic')->nullable();
            $table->string('title_dhivehi')->nullable();
            $table->text('description')->nullable();
            $table->string('level', 16)->default('school');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('student_awards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('award_id');
            $table->unsignedBigInteger('academic_year_id');
            $table->unsignedBigInteger('term_id')->nullable();
            $table->date('awarded_date');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('certificate_document_id')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'academic_year_id'], 'stu_award_student_year_idx');
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('award_id')->references('id')->on('awards')->restrictOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('term_id')->references('id')->on('terms')->nullOnDelete();
            $table->foreign('certificate_document_id')->references('id')->on('documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_awards');
        Schema::dropIfExists('awards');
    }
};
