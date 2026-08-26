<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 course certificates (SPEC §39 / §48). Templates are catalog;
 * issued_certificates are time-scoped (academic_year_id, optional term_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_dv')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('kind', 40);
            $table->unsignedBigInteger('course_id')->nullable();
            $table->json('rules')->nullable();
            $table->text('body_html')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kind', 'active']);
            $table->index('course_id');
        });

        Schema::create('issued_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('certificate_template_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('course_offering_id')->nullable();
            $table->unsignedBigInteger('enrollment_id')->nullable();
            $table->unsignedBigInteger('assessment_id')->nullable();
            $table->unsignedBigInteger('academic_year_id');
            $table->unsignedBigInteger('term_id')->nullable();
            $table->string('public_id', 32)->unique();
            $table->string('certificate_number', 40)->unique();
            $table->date('completion_date');
            $table->string('grade')->nullable();
            $table->unsignedTinyInteger('attendance_percent')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'certificate_template_id']);
            $table->index(['academic_year_id', 'term_id']);
            $table->index('course_offering_id');
        });

        Schema::table('course_offerings', function (Blueprint $table) {
            $table->json('certificate_rules')->nullable()->after('seat_limit');
        });
    }

    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropColumn('certificate_rules');
        });
        Schema::dropIfExists('issued_certificates');
        Schema::dropIfExists('certificate_templates');
    }
};
