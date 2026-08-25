<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * S2.6 — class register loop. Additive year/term columns; legacy
 * course_plans.academic_year string is kept (3-deploy rule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_plans', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('academic_year')->constrained('academic_years')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->after('academic_year_id')->constrained('terms')->nullOnDelete();
        });

        DB::statement('
            UPDATE course_plans
            INNER JOIN academic_years ON academic_years.name = course_plans.academic_year
            SET course_plans.academic_year_id = academic_years.id
            WHERE course_plans.academic_year_id IS NULL
        ');

        Schema::table('lesson_logs', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('classroom_id')->constrained('academic_years')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->after('academic_year_id')->constrained('terms')->nullOnDelete();
            $table->foreignId('timetable_id')->nullable()->after('term_id')->constrained('timetables')->nullOnDelete();
            $table->string('status', 20)->default('expected')->after('timetable_id');
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->timestamp('locked_at')->nullable()->after('submitted_at');
            $table->timestamp('unlocked_until')->nullable()->after('locked_at');
        });

        DB::statement('ALTER TABLE lesson_logs MODIFY taught_summary TEXT NULL');

        Schema::table('lesson_logs', function (Blueprint $table) {
            $table->unique(['timetable_id', 'date'], 'lesson_log_timetable_date');
            $table->index(['status', 'date']);
            $table->index(['academic_year_id', 'date']);
        });

        Schema::create('register_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_log_id')->constrained('lesson_logs')->cascadeOnDelete();
            $table->unsignedBigInteger('unlocked_by');
            $table->text('reason');
            $table->timestamp('unlocked_at');
            $table->timestamps();

            $table->index(['lesson_log_id', 'unlocked_at']);
        });

        DB::table('settings')->insertOrIgnore([
            'key' => 'register_lock_days',
            'value' => '7',
            'type' => 'string',
            'group' => 'academics',
            'label' => 'Days after a lesson date before the register locks',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Permission::firstOrCreate(['name' => 'registers.fill', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'registers.manage', 'guard_name' => 'web']);

        foreach (['super_admin', 'admin', 'headmaster', 'supervisor', 'teacher'] as $roleName) {
            if (! Role::query()->where('name', $roleName)->exists()) {
                continue;
            }

            Role::findByName($roleName)->givePermissionTo('registers.fill');
        }

        foreach (['super_admin', 'admin', 'headmaster', 'supervisor'] as $roleName) {
            if (! Role::query()->where('name', $roleName)->exists()) {
                continue;
            }

            Role::findByName($roleName)->givePermissionTo('registers.manage');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('register_unlocks');

        Schema::table('lesson_logs', function (Blueprint $table) {
            $table->dropUnique('lesson_log_timetable_date');
            $table->dropConstrainedForeignId('timetable_id');
            $table->dropConstrainedForeignId('term_id');
            $table->dropConstrainedForeignId('academic_year_id');
            $table->dropColumn(['status', 'submitted_at', 'locked_at', 'unlocked_until']);
        });

        Schema::table('course_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('term_id');
            $table->dropConstrainedForeignId('academic_year_id');
        });
    }
};
