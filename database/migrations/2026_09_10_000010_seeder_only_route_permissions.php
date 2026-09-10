<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Three permissions the code checks existed only in `RoleSeeder`, and the
 * deploy script does not run seeders.
 *
 * `scripts/pull-deploy-test.sh` runs `php artisan migrate --force` and never
 * `db:seed`, so a permission added to the seeder after a deployment was first
 * set up never reaches it. 31 of the 34 permissions this codebase checks are
 * created by a migration for exactly that reason — including
 * `custom_fields.manage` and `translations.manage`. These three were the
 * outliers, and all three were added *after* the repo started:
 * `events.manage` in August, `messages.broadcast` on 2026-09-08,
 * `forms.manage` on 2026-09-10.
 *
 * The failure is silent and total. `->can('forms.manage')` on a permission
 * that does not exist returns false for **everyone, including super_admin** —
 * Spatie's gate check swallows `PermissionDoesNotExist` and falls through to a
 * Gate with no matching ability, and this app defines no `Gate::before`
 * super-admin bypass. So the sign-up-sheet results, the website events admin
 * and staff broadcast messaging return 403 to every account, with nothing in
 * the log to say why.
 *
 * The role matrix here is transcribed from `RoleSeeder`, not invented:
 * super_admin and admin receive everything there via `Permission::all()`, and
 * the headmaster/supervisor/teacher grants are copied line for line. This
 * migration is the delivery vehicle for a decision already made, not a new one.
 *
 * Additive and idempotent (rule 9): `firstOrCreate` plus Spatie's own
 * duplicate-safe `givePermissionTo`, and roles are granted only where they
 * already exist — on a fresh database this runs before `RoleSeeder` creates
 * them, and the seeder then grants everything anyway.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $permissions = ['events.manage', 'forms.manage', 'messages.broadcast'];

    public function up(): void
    {
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->assignIfRoleExists('super_admin', $this->permissions);
        $this->assignIfRoleExists('admin', $this->permissions);
        $this->assignIfRoleExists('headmaster', ['events.manage', 'forms.manage', 'messages.broadcast']);
        $this->assignIfRoleExists('supervisor', ['events.manage']);
        $this->assignIfRoleExists('teacher', ['forms.manage', 'messages.broadcast']);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function assignIfRoleExists(string $roleName, array $permissions): void
    {
        Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permissions);
    }

    public function down(): void
    {
        // Deleting the rows cascades the role grants, returning the database
        // to the state this migration found: the seeder still declares them.
        Permission::whereIn('name', $this->permissions)->delete();
    }
};
