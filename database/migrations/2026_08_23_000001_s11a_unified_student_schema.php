<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S1.1a — additive Unified Student schema (no backfill).
 *
 * Central database/migrations/ (not a domain folder): domain migration
 * directories are not loaded yet. See ADR-006.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropForeign(['class_id']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable()->change();
            $table->unsignedBigInteger('class_id')->nullable()->change();
            $table->string('student_id')->nullable()->change();
            $table->date('admission_date')->nullable()->change();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreign('school_id')->references('id')->on('schools')->nullOnDelete();
            $table->foreign('class_id')->references('id')->on('classes')->nullOnDelete();
        });

        DB::statement("ALTER TABLE students MODIFY status VARCHAR(32) NOT NULL DEFAULT 'active'");

        Schema::table('students', function (Blueprint $table) {
            $table->string('passport', 50)->nullable()->after('national_id');
            $table->string('email')->nullable()->after('passport');
            $table->string('nationality')->nullable()->default('MV')->after('email');
            $table->string('place_of_birth')->nullable()->after('nationality');
            $table->text('medical_conditions')->nullable()->after('notes');
            $table->text('allergies')->nullable()->after('medical_conditions');
            $table->string('doctor_name')->nullable()->after('allergies');
            $table->string('doctor_phone')->nullable()->after('doctor_name');
            $table->unsignedBigInteger('legacy_registration_student_id')->nullable()->after('doctor_phone');
            $table->index('legacy_registration_student_id');
        });

        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('relationship')->nullable();
            $table->unsignedTinyInteger('priority')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('student_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason')->nullable();
            $table->date('effective_date');
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained('parent_guardians')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('relationship', 32);
            $table->boolean('is_primary')->default(false);
            $table->boolean('can_pickup')->default(true);
            $table->boolean('financial_responsible')->default(false);
            $table->timestamps();
            $table->unique(['guardian_id', 'student_id']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->string('media_path');
            $table->string('document_type', 32);
            $table->string('title')->nullable();
            $table->date('expires_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['documentable_type', 'documentable_id']);
        });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->foreignId('unified_student_id')
                ->nullable()
                ->after('student_id')
                ->constrained('students')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unified_student_id');
        });

        Schema::dropIfExists('documents');
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('student_status_history');
        Schema::dropIfExists('emergency_contacts');

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['legacy_registration_student_id']);
            $table->dropColumn([
                'passport',
                'email',
                'nationality',
                'place_of_birth',
                'medical_conditions',
                'allergies',
                'doctor_name',
                'doctor_phone',
                'legacy_registration_student_id',
            ]);
        });

        // Status stays a string — restoring the MySQL enum would rewrite live values.
    }
};
