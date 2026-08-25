<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1B.3 — offering sessions and attendance foundation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_offering_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained('course_offerings')->restrictOnDelete();
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedBigInteger('term_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('session_type', 32);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('timezone', 64)->default('Indian/Maldives');
            $table->string('location_name')->nullable();
            $table->string('location_address')->nullable();
            $table->string('online_meeting_url')->nullable();
            $table->string('online_meeting_provider')->nullable();
            $table->unsignedBigInteger('teacher_user_id')->nullable();
            $table->boolean('is_required')->default(true);
            $table->string('recording_url')->nullable();
            $table->json('materials')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_offering_id', 'starts_at']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_session_id')->constrained('course_offering_sessions')->restrictOnDelete();
            $table->foreignId('course_offering_id')->constrained('course_offerings')->restrictOnDelete();
            $table->unsignedBigInteger('enrollment_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('attendance_mode', 20)->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['course_offering_session_id', 'enrollment_id'], 'attendance_records_session_enrollment_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('course_offering_sessions');
    }
};
