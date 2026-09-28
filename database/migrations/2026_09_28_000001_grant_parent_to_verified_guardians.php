<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * docs/SIGN_IN_PLAN.md ID2c (finding F14): a guardian's login becomes a
 * `parent` when the office verifies their link to a child
 * (`RecordGuardianLinkPolicyAction`). Before this, nothing granted the role
 * except the role screen and the seeders, so a parent who registered their
 * children on the website held no Family workspace however long ago the
 * office had verified them. This gives the role to every login that already
 * holds a verified link and lacks it. Additive: nobody loses a role, and an
 * unverified link grants nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        $userIds = DB::table('parent_guardians')
            ->join('guardian_student', 'guardian_student.guardian_id', '=', 'parent_guardians.id')
            ->whereNotNull('parent_guardians.user_id')
            ->where('guardian_student.verification_status', 'verified')
            ->distinct()
            ->pluck('parent_guardians.user_id');

        foreach ($userIds as $userId) {
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $role->id,
                // The morph alias `config/morph-map.php` gives the user model (ADR-005).
                'model_type' => 'user',
                'model_id' => (int) $userId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Nothing to undo: which of these roles the office would have given
        // by hand cannot be told apart from the ones given here.
    }
};
