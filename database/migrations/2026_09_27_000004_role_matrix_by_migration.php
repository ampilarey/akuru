<?php

use App\Support\Authorization\RoleGrants;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The whole school role matrix by migration (BACKLOG C10, KNOWN_ISSUES 11,
 * STATUS §5ij).
 *
 * `headmaster`, `teacher`, `student` and `parent` existed only if
 * `RoleSeeder` had run, and deployments run `migrate` without `db:seed` —
 * so every permission-granting migration since the repo started no-oped
 * its grants to those roles on a migrate-only database, and a role change
 * could not be shipped by deploy at all. The dean and the supervisor also
 * still held `prayer.manage`, `daily_content.manage` and
 * `daily_content.approve`, which no route has admitted them to since
 * ADR-040 slice 2, and the educational admin held three Hifz view
 * permissions with no Hifz screen to use them on.
 *
 * Every school role is now created here and **synced** to its set in
 * `RoleGrants` — the lists the seeder reads too — so a migrate-only
 * database and a seeded one hold the same matrix, and a set change is one
 * more migration. `super_admin` receives every permission the matrix
 * names. Rolling back leaves the roles and their sets as they are: the
 * grants it removed were dead, and a role that exists is not undone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $matrix = RoleGrants::matrix();
        $all = array_values(array_unique(array_merge(...array_values($matrix))));

        foreach ($all as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach ($matrix as $role => $permissions) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])->syncPermissions($permissions);
        }

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])->givePermissionTo($all);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Nothing to undo: the roles stay, and their sets are the decision.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
