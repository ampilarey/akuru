<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * LENDING_AND_USED_BOOKS_PLAN L4: the `lender` role. The owner, on Manage
 * users (2026-10-01): "still no lender role". Registering as a lender grants
 * it; the office sees it beside Vendor and Writer and may take it away.
 * Carries no permission — lending's gates are the ID check (D5) and the
 * lender's own row. Anyone already registered gets the role here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::firstOrCreate(['name' => 'lender', 'guard_name' => 'web']);
        $userModel = config('auth.providers.users.model');
        $morph = (new $userModel)->getMorphClass(); // what Spatie writes: the morph-map alias (ADR-005)
        foreach (DB::table('lenders')->pluck('user_id') as $userId) {
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $role->id,
                'model_type' => $morph,
                'model_id' => (int) $userId,
            ]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'lender')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
