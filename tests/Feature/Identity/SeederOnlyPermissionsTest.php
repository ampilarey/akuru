<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * `events.manage`, `forms.manage` and `messages.broadcast` were created only by
 * `RoleSeeder`, and `scripts/pull-deploy-test.sh` runs `migrate --force`
 * without `db:seed`. On a deployment set up before each was added — August,
 * 2026-09-08 and 2026-09-10 respectively — the row does not exist, and
 * `->can()` on a permission with no row is false for **everyone**, super_admin
 * included: Spatie's gate check swallows `PermissionDoesNotExist`, and this app
 * defines no `Gate::before` super-admin bypass.
 *
 * These tests deliberately do **not** use `actingPeopleAdmin()`, which calls
 * `Permission::findOrCreate` and so manufactures the very row whose absence is
 * the defect. That helper is why the existing suite never caught this.
 *
 * `RefreshDatabase` runs migrations and no seeders, which is exactly the state
 * of a deployment that has only ever run `migrate`. Only `super_admin`,
 * `reviewer` and `writer` are created by migrations — the other six roles come
 * from `RoleSeeder` — so the grant matrix is asserted further down against a
 * database seeded to look like the real one.
 */
it('creates the three seeder-only permissions in a migration', function () {
    foreach (['events.manage', 'forms.manage', 'messages.broadcast'] as $permission) {
        expect(Permission::query()->where('name', $permission)->where('guard_name', 'web')->exists())
            ->toBeTrue("{$permission} is missing on a migrate-only database.");
    }
});

it('lets the super administrator reach screens it previously could not', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $superAdmin = $superAdmin->fresh();

    // No explicit givePermissionTo anywhere: the only possible source is the
    // migration's grant to the super_admin role.
    expect($superAdmin->can('events.manage'))->toBeTrue()
        ->and($superAdmin->can('forms.manage'))->toBeTrue()
        ->and($superAdmin->can('messages.broadcast'))->toBeTrue();

    // And the screens actually open, rather than the permission merely existing.
    $this->withoutLocalizationMiddleware()->actingAs($superAdmin)
        ->get(route('academics.events.index'))->assertOk();
    $this->withoutLocalizationMiddleware()->actingAs($superAdmin)
        ->get(route('forms.index'))->assertOk();
});

it('refuses all three to an account with no role', function () {
    $nobody = User::factory()->create();

    expect($nobody->can('events.manage'))->toBeFalse()
        ->and($nobody->can('forms.manage'))->toBeFalse()
        ->and($nobody->can('messages.broadcast'))->toBeFalse();

    $this->withoutLocalizationMiddleware()->actingAs($nobody)
        ->get(route('forms.index'))->assertForbidden();
});

it('grants the three to exactly the roles RoleSeeder declares', function () {
    // Model the real deployment: the six seeder-created roles exist, the three
    // permissions do not. Re-running the migration is safe — it is idempotent
    // by construction (firstOrCreate + Spatie's duplicate-safe grant).
    foreach (['admin', 'headmaster', 'supervisor', 'teacher', 'student', 'parent'] as $name) {
        Role::findOrCreate($name, 'web');
    }
    Permission::query()->whereIn('name', ['events.manage', 'forms.manage', 'messages.broadcast'])->delete();

    $migration = require database_path('migrations/2026_09_10_000010_seeder_only_route_permissions.php');
    $migration->up();

    $can = function (string $role, string $permission): bool {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh()->can($permission);
    };

    // Transcribed from RoleSeeder, which is where the decision was made.
    expect($can('headmaster', 'events.manage'))->toBeTrue()
        ->and($can('headmaster', 'forms.manage'))->toBeTrue()
        ->and($can('headmaster', 'messages.broadcast'))->toBeTrue()
        ->and($can('admin', 'events.manage'))->toBeTrue()
        // Supervisor runs events but not sign-up sheets, and does not broadcast.
        ->and($can('supervisor', 'events.manage'))->toBeTrue()
        ->and($can('supervisor', 'forms.manage'))->toBeFalse()
        ->and($can('supervisor', 'messages.broadcast'))->toBeFalse()
        // A teacher broadcasts to their own classes and runs forms, but does
        // not administer school events.
        ->and($can('teacher', 'forms.manage'))->toBeTrue()
        ->and($can('teacher', 'messages.broadcast'))->toBeTrue()
        ->and($can('teacher', 'events.manage'))->toBeFalse()
        // Families and pupils get none of the three.
        ->and($can('parent', 'events.manage'))->toBeFalse()
        ->and($can('parent', 'messages.broadcast'))->toBeFalse()
        ->and($can('student', 'forms.manage'))->toBeFalse();
});
