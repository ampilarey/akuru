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
    // The courses CMS is the Institute's Blade-shell fixture: the pages CMS is Inertia since C9 slice 10.
    $page = test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.courses.index'))->assertOk();
    $page->assertSee('<title>Courses - '.config('app.name'), false);
    test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.prayer-times.groups.index'))->assertOk()
        ->assertSee('<title>Prayer Times Groups - '.config('app.name'), false);
    // No remaining admin Blade screen names itself: the last two that did (the
    // instructors list and form) left for Inertia in C9 slice 3 (STATUS §5je),
    // so every Blade title now comes from the route. Pinned, so a new
    // `@section('title'` is a deliberate choice rather than a leftover.
    $titled = collect(glob(resource_path('views/admin/**/*.blade.php')))->merge(glob(resource_path('views/admin/**/**/*.blade.php')))
        ->filter(fn ($f) => str_contains((string) file_get_contents($f), "@section('title'"));
    expect($titled->count())->toBe(0);
});

it('gives keyboard and screen-reader users a skip link, labelled menus and a main landmark', function () {
    $admin = layoutAdmin();
    // The Quran progress list is the Blade-shell fixture: the enrolment lists are Inertia since C9 slice 4.
    test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('quran-progress.index'))->assertOk()
        ->assertSee('href="#main"', false)
        ->assertSee('<main id="main">', false)
        ->assertSee('aria-controls="nav-more-menu"', false)
        ->assertSee('aria-label="Menu"', false)
        ->assertSee('id="nav-mobile-menu"', false);
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
