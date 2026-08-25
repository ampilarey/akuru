<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * S3.3 — exam_marks (student-keyed). Roster is class_student as-of exam_date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->unsignedBigInteger('student_id');
            $table->decimal('marks', 8, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->boolean('is_exempt')->default(false);
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('entered_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'student_id'], 'exam_marks_exam_student_uidx');
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
        });

        Permission::firstOrCreate(['name' => 'exams.enter-any', 'guard_name' => 'web']);
        foreach (['super_admin', 'admin', 'headmaster', 'supervisor'] as $roleName) {
            if (Role::query()->where('name', $roleName)->exists()) {
                Role::findByName($roleName)->givePermissionTo('exams.enter-any');
            }
        }

        $now = now();
        DB::table('settings')->insertOrIgnore([
            'key' => 'exams_exclude_absent',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'exams',
            'label' => 'Exclude absences from exam averages (otherwise absent = 0)',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_marks');
    }
};
