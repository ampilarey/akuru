<?php

use App\Domains\Identity\Models\User;
use App\Support\Authorization\RoleGrants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The whole school role matrix by migration (BACKLOG C10, KNOWN_ISSUES 11,
 * STATUS §5ij). `headmaster`, `teacher`, `student` and `parent` existed
 * only if the seeder had run, so no role change could be shipped by deploy;
 * the dean and the supervisor held website grants no route admitted them
 * to; the educational admin counted as a Hifz dean by role while holding
 * none of the Hifz permissions.
 */
function heldBy(string $role): array
{
    return Role::findByName($role)->permissions()->pluck('name')->sort()->values()->all();
}

function sorted(array $list): array
{
    sort($list);

    return array_values($list);
}

it('creates every school role with exactly its set on a migrate-only database', function () {
    foreach (RoleGrants::matrix() as $role => $set) {
        expect(heldBy($role))->toBe(sorted($set), $role);
    }

    // The dead grants are gone, the live ones stay.
    expect(heldBy('headmaster'))->not->toContain('prayer.manage', 'daily_content.manage', 'daily_content.approve')
        ->toContain('exams.manage', 'finance.manage', 'manage_hifz_programs', 'courses.manage')
        ->and(heldBy('supervisor'))->not->toContain('prayer.manage', 'daily_content.manage')
        ->toContain('courses.manage', 'courses.publish', 'registers.manage')
        ->and(heldBy('admin'))->not->toContain('view_hifz_programs', 'view_hifz_reports', 'export_hifz_reports')
        ->and(heldBy('teacher'))->toContain('registers.fill', 'mark_attendance', 'create_hifz_sessions')
        ->and(heldBy('parent'))->toContain('requests.submit')->not->toContain('registers.fill');

    // The system admin holds all of it.
    $super = User::factory()->create();
    $super->assignRole('super_admin');
    foreach (['manage_hifz_programs', 'courses.publish', 'payroll.approve', 'mark_attendance'] as $permission) {
        expect($super->fresh()->can($permission))->toBeTrue($permission);
    }
});

it('seeds the same matrix, removing what an older grant left behind', function () {
    // Re-seeding must not re-widen a role: give the dean a dead grant and a
    // role of the old shape, then seed.
    Permission::findOrCreate('prayer.manage', 'web');
    Role::findByName('headmaster')->givePermissionTo('prayer.manage');
    test()->seed(\Database\Seeders\RoleSeeder::class);

    foreach (RoleGrants::matrix() as $role => $set) {
        expect(heldBy($role))->toBe(sorted($set), $role);
    }
});

it('keeps Hifz with the dean: the educational admin is no longer a dean by role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $dean = User::factory()->create();
    $dean->assignRole('headmaster');

    expect($admin->fresh()->isHifzDean())->toBeFalse()->and($dean->fresh()->isHifzDean())->toBeTrue();

    // The hub has nowhere to send an educational admin, and the office's
    // menu does not offer Hifz (the item needs `view_hifz_programs`).
    test()->withoutLocalizationMiddleware()->actingAs($admin->fresh())->get(route('hifz.hub'))->assertForbidden();
    test()->withoutLocalizationMiddleware()->actingAs($admin->fresh())->get(route('hifz.dean.dashboard'))->assertForbidden();
    $nav = app(\App\Support\Navigation\BuildNavigationAction::class)->execute($admin->fresh(), 'en');
    $hrefs = [];
    foreach ($nav['groups'] as $group) {
        $hrefs = [...$hrefs, ...array_column($group['items'], 'href')];
    }
    expect($hrefs)->not->toContain('/hifz');

    test()->withoutLocalizationMiddleware()->actingAs($dean->fresh())->get(route('hifz.hub'))->assertRedirect(route('hifz.dean.dashboard'));
});
