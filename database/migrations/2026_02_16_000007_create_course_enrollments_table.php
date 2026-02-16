<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('registration_students')->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('term_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'active', 'completed'])->default('pending');
            $table->timestamp('enrolled_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('payment_status', ['not_required', 'required', 'pending', 'confirmed'])->default('not_required');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('set null');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE course_enrollments ADD COLUMN term_key INT GENERATED ALWAYS AS (IFNULL(term_id, 0)) STORED');
        DB::statement('ALTER TABLE course_enrollments ADD UNIQUE KEY course_enrollments_student_course_term_unique (student_id, course_id, term_key)');
    }

    public function down(): void
    {
        Schema::dropIfExists('course_enrollments');
    }
};
