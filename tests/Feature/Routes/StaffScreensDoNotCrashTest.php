<?php

use App\Domains\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Every screen that is not family-facing loads for somebody allowed to see it.
 *
 * The sibling of `PublicPagesDoNotCrashTest`, and built for the same reason:
 * on 2026-09-10 two public pages returned 500 to every visitor while 1,100
 * tests passed, because no test loaded them.
 *
 * **What this asserts is narrow on purpose: no 5xx.** Not that a screen is
 * correct, not that it shows anything useful — a page can render an empty grid
 * and pass here. It catches the failure that makes every other question moot.
 * 403 and 302 are both allowed: several screens are deliberately gated on a
 * feature flag rather than a permission (payroll's kill-switch, ADR-016, being
 * the documented one), the Hifz role dashboards are gated on roles a
 * super_admin does not hold, and the auth flows redirect a signed-in user away.
 *
 * The set of screens is no longer a list kept here. It is `staffScreens()` in
 * `ScreenCensusHelpers`, defined by subtraction — every screen that is not
 * family-facing — because the hand-written prefix list this test used to carry
 * had quietly missed forty of them.
 */
it('loads every staff screen without a server error', function () {
    // The real roles, not one invented for the test. An earlier draft created
    // only `super_admin`, and `hifz/dean` and `hifz/programs/create` both threw
    // `RoleDoesNotExist: supervisor` — a fixture artefact that reads exactly
    // like a product defect. Seeding the roles the app actually ships with is
    // what makes a 500 here mean something.
    $this->seed(RoleSeeder::class);

    $role = Role::findOrCreate('super_admin', 'web');
    $role->givePermissionTo(Permission::all());

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $screens = staffScreens();

    // If this drops to nothing the test has stopped testing anything — the
    // shape of the route file changed and the census quietly matched zero.
    expect(count($screens))->toBeGreaterThan(120);

    $crashed = [];

    foreach ($screens as [$name, $uri]) {
        try {
            $response = $this->withoutLocalizationMiddleware()
                ->actingAs($user->fresh())
                ->get('/'.$uri);

            if ($response->getStatusCode() >= 500) {
                $crashed[] = sprintf('%s (%s) → %d', $uri, $name, $response->getStatusCode());
            }
        } catch (Throwable $e) {
            $crashed[] = sprintf('%s (%s) threw %s: %s', $uri, $name, $e::class, $e->getMessage());
        }
    }

    expect($crashed)->toBeEmpty(
        count($crashed)." staff screen(s) return a server error:\n".implode("\n", $crashed)
    );
});
