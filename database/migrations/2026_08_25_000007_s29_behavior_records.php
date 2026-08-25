<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * S2.9 — behavior records. Teachers record; admins edit/delete with audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavior_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->string('type', 20);
            $table->string('category');
            $table->text('description');
            $table->smallInteger('points')->nullable();
            $table->date('date');
            $table->unsignedBigInteger('recorded_by');
            $table->boolean('parent_visible')->default(true);
            $table->boolean('requires_followup')->default(false);
            $table->text('followup_notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'date']);
            $table->index(['academic_year_id', 'type']);
        });

        Schema::create('behavior_record_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('behavior_record_id')->nullable()->constrained('behavior_records')->nullOnDelete();
            $table->unsignedBigInteger('actor_id');
            $table->string('action', 20);
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        DB::table('settings')->insertOrIgnore([
            'key' => 'behavior_categories',
            'value' => json_encode(['conduct', 'homework', 'uniform', 'safety', 'other']),
            'type' => 'json',
            'group' => 'academics',
            'label' => 'Behavior record categories',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Permission::firstOrCreate(['name' => 'behavior.record', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'behavior.manage', 'guard_name' => 'web']);

        foreach (['super_admin', 'admin', 'headmaster', 'supervisor', 'teacher'] as $roleName) {
            if (Role::query()->where('name', $roleName)->exists()) {
                Role::findByName($roleName)->givePermissionTo('behavior.record');
            }
        }

        foreach (['super_admin', 'admin', 'headmaster'] as $roleName) {
            if (Role::query()->where('name', $roleName)->exists()) {
                Role::findByName($roleName)->givePermissionTo('behavior.manage');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('behavior_record_audits');
        Schema::dropIfExists('behavior_records');
    }
};
