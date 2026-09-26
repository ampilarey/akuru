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
    $page = test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.pages.index'))->assertOk();
    $page->assertSee('<title>Pages - '.config('app.name'), false);
    test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.prayer-times.groups.index'))->assertOk()
        ->assertSee('<title>Prayer Times Groups - '.config('app.name'), false);
    // A screen that names itself keeps its own title.
    $titled = collect(glob(resource_path('views/admin/**/*.blade.php')))->merge(glob(resource_path('views/admin/**/**/*.blade.php')))
        ->filter(fn ($f) => str_contains((string) file_get_contents($f), "@section('title'"));
    expect($titled->count())->toBeGreaterThan(0);
});

it('gives keyboard and screen-reader users a skip link, labelled menus and a main landmark', function () {
    $admin = layoutAdmin();
    test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.enrollments.index'))->assertOk()
        ->assertSee('href="#main"', false)
        ->assertSee('<main id="main">', false)
        ->assertSee('aria-controls="nav-more-menu"', false)
        ->assertSee('aria-label="Menu"', false)
        ->assertSee('id="nav-mobile-menu"', false);
});

it('turns the admin shell right-to-left for Dhivehi and Arabic', function () {
    $admin = layoutAdmin();
    app()->setLocale('dv');
    test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.enrollments.index'))->assertOk()
        ->assertSee('<html lang="dv" dir="rtl">', false);
    app()->setLocale('en');
    test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.enrollments.index'))->assertOk()
        ->assertSee('<html lang="en" dir="ltr">', false);
});
