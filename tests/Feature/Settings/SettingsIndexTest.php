<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * ST1 (admin audit 2026-11-27, STATUS §5ut): /admin/settings is an index —
 * a card per product's own settings page, shown only to whoever that page
 * admits — and the system's own page moved to /admin/settings/system.
 */
uses(RefreshDatabase::class);

it('gives the super admin a card per product, the School\'s apart, and edits nothing itself', function () {
    $web = test()->withoutLocalizationMiddleware()->actingAs(actingSystemAdmin(['bookshop.manage', 'library.manage']));

    $web->get('/admin/settings')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Settings/Home')
        ->where('cards', fn ($cards) => collect($cards)->pluck('href')->all() === [
            '/admin/settings/system', '/admin/library/settings', '/admin/settings/office/blogs', '/admin/bookshop/settings', '/admin/settings/office/bookstore', '/admin/settings/office/bookstore_money', '/admin/bookshop/print', '/admin/settings/office/lending', '/admin/settings/office/wallet', '/admin/translations',
            '/finance/settings', '/hr/settings',
        ] && collect($cards)->where('group', 'school')->count() === 2
            && collect($cards)->every(fn ($c) => $c['title'] !== '' && ! str_starts_with($c['title'], 'admin.') && ! str_starts_with($c['description'], 'admin.')))
        ->missing('settings')->missing('backup')->has('t.settings_index_title'));

    // Every card opens.
    foreach (['/admin/settings/system', '/admin/library/settings', '/admin/settings/office/blogs', '/admin/bookshop/settings', '/admin/settings/office/bookstore', '/admin/settings/office/bookstore_money', '/admin/bookshop/print', '/admin/settings/office/lending', '/admin/settings/office/wallet', '/admin/translations', '/finance/settings', '/hr/settings'] as $href) {
        $web->get($href)->assertOk();
    }
    $web->get('/admin/settings/system')->assertInertia(fn (Assert $page) => $page->component('Settings/Index')->has('backup')->has('t.settings_all'));
});

it('shows each person only the cards their own gates admit, and refuses whoever has none', function () {
    \Spatie\Permission\Models\Role::findOrCreate('bookshop_manager', 'web');
    \Spatie\Permission\Models\Permission::findOrCreate('bookshop.manage', 'web');
    $manager = User::factory()->create();
    $manager->assignRole('bookshop_manager');
    $manager->givePermissionTo('bookshop.manage');
    $web = test()->withoutLocalizationMiddleware();

    $web->actingAs($manager)->get('/admin/settings')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('cards', fn ($cards) => collect($cards)->pluck('key')->all() === ['bookshop_settings', 'bookstore_rules', 'print_settings', 'lending_settings']));
    $web->actingAs($manager)->get('/admin/settings/system')->assertForbidden();

    $web->actingAs(User::factory()->create())->get('/admin/settings')->assertForbidden();

    // A school-only admin holds the School's settings, not the Institute's: no index (ADR-040).
    \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
    $school = User::factory()->create();
    $school->assignRole('admin');
    $web->actingAs($school)->get('/finance/settings')->assertOk();
    $web->actingAs($school)->get('/admin/settings')->assertForbidden();
});
