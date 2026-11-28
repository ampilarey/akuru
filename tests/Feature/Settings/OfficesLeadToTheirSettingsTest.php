<?php

use App\Domains\Identity\Models\User;
use App\Support\Navigation\NavigationMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * ST5 (admin audit 2026-11-27, STATUS §5uy): the Blogs office is its own
 * section, out from under the Library's; the Settings part lists settings
 * only; and each office leads to its own settings pages — only those the
 * person may open.
 */
uses(RefreshDatabase::class);

it('makes the Blogs office its own section, and the Settings part settings only', function () {
    $money = collect(NavigationMap::adminPanel())->firstWhere('key', 'panel_money')['items'];
    $library = collect($money)->firstWhere('key', 'library_office');
    $blogs = collect($money)->firstWhere('key', 'blogs_office');
    expect($blogs['href'])->toBe('/admin/library/blog-insights')
        ->and(array_column($blogs['children'], 'href'))->toBe(['/admin/library/blog-requests', '/admin/library/blog-pages', '/admin/library/comments', '/admin/library/tips'])
        ->and(collect($library['children'])->pluck('key')->filter(fn ($key) => str_starts_with($key, 'blog_'))->all())->toBe([]);

    $settings = collect(NavigationMap::adminPanel())->firstWhere('key', 'panel_settings')['items'];
    expect(array_column($settings, 'href'))->toBe(array_values(array_unique(array_column($settings, 'href'))))
        ->and(collect($settings)->every(fn ($item) => empty($item['children']) && ($item['key'] === 'translations' || str_contains($item['key'], 'settings') || str_starts_with($item['key'], 'bookstore_'))))->toBeTrue();
    foreach (['en', 'dv', 'ar'] as $locale) {
        foreach (['blogs_office', 'print_settings'] as $key) {
            expect(trans("nav.{$key}", [], $locale))->not->toBe("nav.{$key}");
        }
        foreach (['insights', 'requests', 'pages', 'comments', 'tips', 'settings'] as $tab) {
            expect(trans("blog.office_tab_{$tab}", [], $locale))->not->toBe("blog.office_tab_{$tab}");
        }
    }
});

it('gives each office the button to its own settings, and the settings the way back', function () {
    $web = test()->withoutLocalizationMiddleware()->actingAs(actingSystemAdmin(['bookshop.manage', 'commerce.manage', 'library.manage']));

    $web->get('/admin/lending')->assertOk()->assertInertia(fn (Assert $page) => $page->where('settings_links', [['key' => 'lending_settings', 'href' => '/admin/settings/office/lending', 'title' => 'Lending']]));
    $web->get('/admin/commerce')->assertOk()->assertInertia(fn (Assert $page) => $page->where('settings_links.0.href', '/admin/settings/office/wallet')->has('settings_links', 1));
    $web->get('/admin/bookshop/settings')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('settings_links', fn ($links) => collect($links)->pluck('key')->all() === ['bookstore_rules', 'bookstore_money', 'print_settings']));

    foreach (['lending' => '/admin/lending', 'wallet' => '/admin/commerce', 'bookstore' => '/admin/bookshop', 'bookstore_money' => '/admin/bookshop', 'blogs' => '/admin/library/blog-insights'] as $product => $office) {
        $web->get("/admin/settings/office/{$product}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('office.href', $office)->where('office.label', fn ($label) => $label !== '' && ! str_starts_with($label, 'nav.')));
        $web->get($office)->assertOk();
    }
});

it('offers a Bookstore manager only the settings pages they may open', function () {
    \Spatie\Permission\Models\Role::findOrCreate('bookshop_manager', 'web');
    \Spatie\Permission\Models\Permission::findOrCreate('bookshop.manage', 'web');
    $manager = User::factory()->create();
    $manager->assignRole('bookshop_manager');
    $manager->givePermissionTo('bookshop.manage');

    test()->withoutLocalizationMiddleware()->actingAs($manager)->get('/admin/bookshop/settings')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('settings_links', fn ($links) => collect($links)->pluck('key')->all() === ['bookstore_rules', 'print_settings']));
});
