<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Every staff screen loads for somebody allowed to see it.
 *
 * The sibling of `PublicPagesDoNotCrashTest`, and built for the same reason:
 * on 2026-09-10 two public pages returned 500 to every visitor while 1,100
 * tests passed, because no test loaded them. The staff side had the same hole
 * — a browser sweep of 116 screens was needed to find out whether any of them
 * crashed, and that sweep was a one-off.
 *
 * **What this asserts is narrow on purpose: no 5xx.** Not that a screen is
 * correct, not that it shows anything useful — a page can render an empty grid
 * and pass here. It catches the failure that makes every other question moot.
 *
 * 403 is allowed and expected: several screens are deliberately gated on a
 * feature flag rather than a permission, payroll's kill-switch (ADR-016) being
 * the documented one. A redirect is allowed too — plenty of index routes
 * forward to a default sub-page.
 */

/** @return list<array{0: string, 1: string}> name + uri */
function staffScreens(): array
{
    $prefixes = ['academics/', 'people/', 'catalog/', 'exams/', 'admin/', 'circulation', 'hr/', 'finance/'];
    $skip = ['export', 'photo', 'file', 'download', 'csv', 'pdf', 'print'];

    $screens = [];

    foreach (RouteFacade::getRoutes() as $route) {
        $uri = $route->uri();
        $name = $route->getName() ?? '';

        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }

        // Anything with a parameter needs a real row to point at; those belong
        // in their own slice's tests, which know what to create.
        if (str_contains($uri, '{')) {
            continue;
        }

        if (! collect($prefixes)->contains(fn (string $p): bool => str_starts_with($uri, $p))) {
            continue;
        }

        if (collect($skip)->contains(fn (string $s): bool => str_contains($uri, $s))) {
            continue;
        }

        $screens[] = [$name, $uri];
    }

    return array_values(array_unique($screens, SORT_REGULAR));
}

it('loads every staff screen without a server error', function () {
    $role = Role::findOrCreate('super_admin', 'web');
    $role->givePermissionTo(Permission::all());

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $screens = staffScreens();

    // If this drops to nothing the test has stopped testing anything — the
    // shape of the route file changed and the filter above quietly matched
    // zero routes.
    expect(count($screens))->toBeGreaterThan(50);

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
