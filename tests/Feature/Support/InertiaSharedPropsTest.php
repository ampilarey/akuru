<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * SPEC §53 "Testing Scope" lists nine Phase 1A areas to test. Eight have
 * coverage; **the ninth had none**:
 *
 *   > 9. Inertia shared props
 *   >
 *   > Test:
 *   >  - Auth user shared correctly
 *   >  - Locale shared correctly
 *   >  - Direction shared correctly
 *   >  - Permissions summary shared correctly
 *
 * `HandleInertiaRequests::share()` runs on **every Inertia response in the
 * application**, and before this file nothing asserted what it returns.
 * `auth.user.id` appeared once, incidentally, in a logout test;
 * `auth.linked_accounts` had its own feature test; `locale_urls` was checked by
 * the i18n preview. The `rtl` direction flag and the `auth.can` permissions
 * summary — two of §53's four named items — were asserted nowhere at all.
 *
 * All four were **behaving correctly** when this was written, which is the
 * reason to pin them rather than a reason not to. A shared prop is the one
 * kind of contract whose blast radius is the whole app: nothing here belongs
 * to a page, so no page's test would catch a change, and the failure mode is
 * every screen at once.
 *
 * The key set on `auth.user` is pinned **exactly**, not loosely. It is
 * `$request->user()->only(['id', 'name', 'email'])` today; widening it to
 * `$request->user()` — a one-word edit that looks like a simplification —
 * would serialize the entire users row into the payload of every page the
 * application renders. An assertion that merely checked `id` was present
 * would not notice.
 */
it('shares the auth user as exactly id, name and email — and nothing else', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    makeYear();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.years.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $admin->id)
            ->where('auth.user.name', $admin->name)
            ->where('auth.user.email', $admin->email)
            // The whole point: a widened `only()` fails here, not in
            // production six months later.
            ->count('auth.user', 3)
        );
});

it('shares no auth user for a guest rather than erroring', function () {
    // `share()` is called directly here rather than through a request, and the
    // reason is worth recording: **there is no guest-reachable Inertia page.**
    // The public site (`/en`, login, admissions) is Blade, and every Inertia
    // route sits behind `auth` or a role. So this branch cannot be reached by
    // getting a URL today.
    //
    // It is still worth pinning. `share()` dereferences the user five times —
    // `->only()`, two `->can()`, the alternate identity, the linked accounts,
    // the unread count — and the day one Inertia page becomes public, a single
    // missing `?` here 500s it rather than rendering it logged-out.
    $request = Illuminate\Http\Request::create('/en/dashboard');
    $request->setUserResolver(fn () => null);
    $request->setLaravelSession(app('session.store'));

    $shared = app(App\Http\Middleware\HandleInertiaRequests::class)->share($request);

    expect($shared['auth']['user'])->toBeNull()
        ->and($shared['auth']['can']['operations_manage'])->toBeFalse()
        ->and($shared['auth']['can']['translations_manage'])->toBeFalse()
        ->and($shared['auth']['unread_notifications'])->toBe(0)
        ->and($shared['rtl'])->toBeFalse();
});

it('shares the locale and the direction that follows from it', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    makeYear();

    // Arabic and Dhivehi are right-to-left; English is not. CLAUDE.md requires
    // every screen to be "trilingual-ready (EN/DV/AR) and RTL-safe", and `rtl`
    // is the single prop the whole front end reads to decide that.
    $expected = ['en' => false, 'dv' => true, 'ar' => true];

    foreach ($expected as $locale => $isRtl) {
        app()->setLocale($locale);

        $this->withoutLocalizationMiddleware()
            ->actingAs($admin)
            ->get(route('academics.years.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', $locale)
                ->where('rtl', $isRtl)
                ->where('locales', ['en', 'dv', 'ar'])
            );
    }
});

it('shares a permissions summary that follows the actual grant', function () {
    makeYear();

    // Granted.
    $withPermission = actingPeopleAdmin(['operations.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($withPermission)
        ->get(route('academics.years.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can.operations_manage', true)
        );

    // Not granted. Without this half the test would pass against a summary
    // hard-coded to true, which is the mistake worth catching: these drive
    // whether nav entries appear.
    $withoutPermission = actingPeopleAdmin(['courses.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($withoutPermission)
        ->get(route('academics.years.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can.operations_manage', false)
        );
});
