<?php

use App\Support\Authorization\RoleGrants;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The educational admin's permission set (ADR-040 slice 2, STATUS §5ie).
 *
 * `RoleSeeder` granted `admin` `Permission::all()` — identical to
 * `super_admin` — while its own comment said "most permissions (school
 * operations, not system-level)" (KNOWN_ISSUES 10). Since the workspaces
 * (slice 1) the menus already hid the Institute from an educational admin;
 * the routes still admitted them. This migration is the delivery vehicle
 * for the owner's decision of 2026-09-27: `admin` runs the school's office
 * and reads its academics, and holds nothing of the website, the shops,
 * the library office or the system.
 *
 * The set lives in `RoleGrants::educationalAdmin()`, which the seeder reads
 * too. `syncPermissions` rather than `givePermissionTo`: the point is what
 * the role *stops* holding. The role is created here if it is missing, so a
 * migrate-only database gets a correctly scoped `admin` (KNOWN_ISSUES 11,
 * for this role). `super_admin` receives every permission the set names,
 * so nothing created here is refused to the system admin on a database that
 * never ran the seeder.
 *
 * Rolling back restores what this migration found: the blanket grant.
 */
return new class extends Migration
{
    public function up(): void
    {
        $set = RoleGrants::educationalAdmin();

        foreach ($set as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->syncPermissions($set);
        Role::where('name', 'super_admin')->where('guard_name', 'web')->first()?->givePermissionTo($set);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'admin')->where('guard_name', 'web')->first()?->syncPermissions(Permission::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
