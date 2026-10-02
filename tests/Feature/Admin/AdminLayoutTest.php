<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The admin panel's Blade layout (the layout audit, STATUS §5ht,
 * docs/ADMIN_PANEL.md §6): a tab title from the route when the screen sets
 * none, a skip link to `#main`, menus that say whether they are open, and a
 * right-to-left page when the locale is.
 */
function layoutAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('super_admin', 'web'));

    return $user;
}

it('titles every admin Blade screen from its route when the screen sets none', function () {
    $admin = layoutAdmin();
    // Every admin screen and both dashboards are Inertia since C9 slices 12
    // and 13 (STATUS §5jn, §5jo). The Blade shell survives on the School's
    // staff screens, which the system admin may open too; the substitution
    // requests list sets no title of its own, so the route names the tab.
    test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('substitutions.requests.index'))->assertOk()
        ->assertSee('<title>Substitutions Requests - '.config('app.name'), false);
    // No admin Blade screen names itself — there is none left to (the last
    // two that did, the instructors list and form, left for Inertia in C9
    // slice 3, STATUS §5je). Pinned, so a new `views/admin/**` Blade screen
    // with a `@section('title'` is a deliberate choice rather than a leftover.
    $titled = collect(glob(resource_path('views/admin/**/*.blade.php')))->merge(glob(resource_path('views/admin/**/**/*.blade.php')))
        ->filter(fn ($f) => str_contains((string) file_get_contents($f), "@section('title'"));
    expect($titled->count())->toBe(0);
});

it('gives keyboard and screen-reader users a skip link, labelled menus and a main landmark', function () {
    $admin = layoutAdmin();
    // The Quran progress list is the Blade-shell fixture: the enrolment lists are Inertia since C9 slice 4.
    $html = test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('quran-progress.index'))->assertOk()
        ->assertSee('href="#main"', false)
        ->assertSee('<main id="main">', false)
        ->assertSee('aria-controls="nav-more-menu"', false)
        ->assertSee('aria-label="Menu"', false)
        ->assertSee('id="nav-mobile-menu"', false)
        ->getContent();
    $initial = strtoupper(substr($admin->navLabel(), 0, 1));
    expect($html)->toMatch('/aria-controls="nav-mobile-menu"[^>]*>\s*'.preg_quote($initial, '/').'\s*</');
    expect($html)->not->toContain('M4 6h16M4 12h16M4 18h16');
});

it('turns the admin shell right-to-left for Dhivehi and Arabic', function () {
    $admin = layoutAdmin();
    app()->setLocale('dv');
    test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('quran-progress.index'))->assertOk()
        ->assertSee('<html lang="dv" dir="rtl">', false);
    app()->setLocale('en');
    test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('quran-progress.index'))->assertOk()
        ->assertSee('<html lang="en" dir="ltr">', false);
});
