<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Every family- and teacher-facing screen loads for the person it is for.
 *
 * `StaffScreensDoNotCrashTest` takes every screen that is not family-facing.
 * This takes the other half — `portal/`, `learn` and `teach/`, the ones parents
 * actually open — and walks them as the people they are built for.
 *
 * **Walked as the real audience, never as a super_admin.** An administrator
 * holding every permission would sail through screens whose scoping is broken
 * for the person they are built for, which is the failure worth catching here:
 * a parent's page that works only because the tester could see everything.
 *
 * The assertion is narrow on purpose — **no 5xx** — matching its sibling. 403
 * is allowed: a parent opening `portal/overview` or `teach/assignments` should
 * be refused, and several of these screens are staff-only by design.
 *
 * The screen list comes from `familyScreens()` in `ScreenCensusHelpers`. It
 * used to be built here from the prefixes `['portal/', 'learn', 'teach']`, and
 * the last of those was a bug: as a bare string it also matches `teachers/`,
 * so the staff teacher CRUD was being swept as a family screen — and, since no
 * guard's prefix list contained `teachers` either, it was swept *only* as a
 * family screen, by three roles who are all correctly refused it. The census
 * matches on segment boundaries.
 */
it('loads every family and teacher screen without a server error', function () {
    $cast = portalCast();
    $screens = familyScreens();

    expect(count($screens))->toBeGreaterThan(30);

    $crashed = [];

    foreach (['parent', 'student', 'teacher'] as $who) {
        $user = $cast[$who];
        foreach ($screens as [$name, $uri]) {
            try {
                $response = $this->withoutLocalizationMiddleware()
                    ->actingAs($user->fresh())
                    ->get('/'.$uri);

                if ($response->getStatusCode() >= 500) {
                    $crashed[] = sprintf('%s → %s (%s) → %d', $who, $uri, $name, $response->getStatusCode());
                }
            } catch (Throwable $e) {
                $crashed[] = sprintf('%s → %s threw %s: %s', $who, $uri, $e::class, $e->getMessage());
            }
        }
    }

    expect($crashed)->toBeEmpty(
        count($crashed)." family/teacher screen(s) return a server error:\n".implode("\n", $crashed)
    );
});
