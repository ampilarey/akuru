<?php

use App\Domains\ExamsGrades\Enums\ExamTypeCode;
use App\Domains\ExamsGrades\Enums\GradeScaleType;
use App\Domains\ExamsGrades\Models\ExamType;
use App\Domains\ExamsGrades\Models\GradeScale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * S3.1 — grade scales, exam types, assessment weight schemes.
 * No student-keyed rows. Domain folders still unwired — central migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 32);
            $table->json('bands');
            $table->boolean('active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('exam_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_arabic')->nullable();
            $table->string('name_dhivehi')->nullable();
            $table->string('code', 32)->unique();
            $table->unsignedSmallInteger('default_weight')->default(0);
            $table->boolean('counts_toward_final')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('assessment_weight_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('weights');
            $table->timestamps();

            $table->index(['academic_year_id', 'class_id', 'subject_id'], 'aws_year_class_subject_idx');
        });

        Permission::firstOrCreate(['name' => 'exams.manage', 'guard_name' => 'web']);
        foreach (['super_admin', 'admin', 'headmaster', 'supervisor'] as $roleName) {
            if (Role::query()->where('name', $roleName)->exists()) {
                Role::findByName($roleName)->givePermissionTo('exams.manage');
            }
        }

        GradeScale::query()->create([
            'name' => 'Default percentage',
            'type' => GradeScaleType::PercentageBands,
            'bands' => [
                ['min' => 85, 'grade' => 'A', 'point' => 4.0, 'descriptor_en' => 'Excellent', 'descriptor_dv' => null, 'descriptor_ar' => null],
                ['min' => 70, 'grade' => 'B', 'point' => 3.0, 'descriptor_en' => 'Good', 'descriptor_dv' => null, 'descriptor_ar' => null],
                ['min' => 55, 'grade' => 'C', 'point' => 2.0, 'descriptor_en' => 'Satisfactory', 'descriptor_dv' => null, 'descriptor_ar' => null],
                ['min' => 40, 'grade' => 'D', 'point' => 1.0, 'descriptor_en' => 'Pass', 'descriptor_dv' => null, 'descriptor_ar' => null],
                ['min' => 0, 'grade' => 'E', 'point' => 0.0, 'descriptor_en' => 'Fail', 'descriptor_dv' => null, 'descriptor_ar' => null],
            ],
            'active' => true,
            'is_default' => true,
        ]);

        foreach ([
            [ExamTypeCode::Midterm, 'Midterm', 30],
            [ExamTypeCode::Final, 'Final', 40],
            [ExamTypeCode::Quiz, 'Quiz', 10],
            [ExamTypeCode::Assignment, 'Assignment', 10],
            [ExamTypeCode::Practical, 'Practical', 5],
            [ExamTypeCode::Oral, 'Oral', 5],
        ] as [$code, $name, $weight]) {
            ExamType::query()->create([
                'name' => $name,
                'code' => $code,
                'default_weight' => $weight,
                'counts_toward_final' => true,
                'active' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_weight_schemes');
        Schema::dropIfExists('exam_types');
        Schema::dropIfExists('grade_scales');
    }
};
