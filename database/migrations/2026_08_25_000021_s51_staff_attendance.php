<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * S5.1 — staff attendance writer + department/designation on staff_profiles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->string('department')->nullable()->after('status');
            $table->string('designation')->nullable()->after('department');
        });

        Schema::create('staff_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('status', 20);
            $table->string('source', 20);
            $table->unsignedSmallInteger('minutes_late')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['staff_profile_id', 'date'], 'staff_att_staff_date_uq');
            $table->index(['academic_year_id', 'date'], 'staff_att_year_date_idx');
            $table->index(['status', 'date'], 'staff_att_status_date_idx');
        });

        Permission::firstOrCreate(['name' => 'hr.manage', 'guard_name' => 'web']);
        foreach (['super_admin', 'admin', 'headmaster'] as $roleName) {
            if (Role::query()->where('name', $roleName)->exists()) {
                Role::findByName($roleName)->givePermissionTo(['hr.manage']);
            }
        }

        $now = now();
        DB::table('settings')->insertOrIgnore([
            'key' => 'hr.staff_self_checkin',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'hr',
            'label' => 'Allow staff self check-in from the portal',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendance');

        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropColumn(['department', 'designation']);
        });
    }
};
