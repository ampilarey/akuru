<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Phase 4 P4.4 (SPEC §49 close-out): offering price override ("Offerings
 * may override course price" — override 0 makes an offering free) and the
 * payments.record permission for manual payment recording. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->decimal('price_override', 10, 2)->nullable()->after('seat_limit');
        });

        Permission::firstOrCreate(['name' => 'payments.record', 'guard_name' => 'web']);
        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo('payments.record');
        }
    }

    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropColumn('price_override');
        });
    }
};
