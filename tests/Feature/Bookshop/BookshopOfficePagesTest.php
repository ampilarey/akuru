<?php

use App\Domains\Bookshop\Actions\PresentBookshopOfficeAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * ST2 (admin audit 2026-11-27, STATUS §5uu): the Bookstore office is six
 * pages, each with its own lists and nothing of the others'.
 */
uses(RefreshDatabase::class);

it('opens each office page with its own lists only, and the tabs on every one', function () {
    $web = test()->withoutLocalizationMiddleware()->actingAs(actingSystemAdmin(['bookshop.manage']));
    $own = [
        'today' => ['slips', 'refunds', 'applications', 'listings', 'identity_checks', 'orders', 'quotes'],
        'shops' => ['vendors', 'team', 'hosts', 'default_commission_rate'],
        'storefront' => ['home', 'catalogue'],
        'checks' => ['custom_css', 'themes', 'reviews', 'questions'],
        'money' => ['money', 'refunds'],
        'reports' => ['insights', 'low_stock'],
    ];
    expect(array_keys($own))->toBe(PresentBookshopOfficeAction::TABS);

    foreach ($own as $tab => $props) {
        $others = array_diff(array_merge(...array_values($own)), $props, $tab === 'today' ? ['vendors'] : []);
        $web->get($tab === 'today' ? '/admin/bookshop' : '/admin/bookshop/office/'.$tab)->assertOk()
            ->assertInertia(function (Assert $page) use ($tab, $props, $others) {
                $page->component('Bookshop/Admin')->where('tab', $tab)->has('tabs', 6)->where('tabs.0.href', '/admin/bookshop')
                    ->has('t.office_tab_'.$tab)->has('t.office_tab_'.$tab.'_intro');
                foreach ($props as $prop) {
                    $page->has($prop);
                }
                foreach ($others as $prop) {
                    $page->missing($prop);
                }
            });
    }
});

it('keeps every office page to the Bookstore\'s managers, and has no page by another name', function () {
    $web = test()->withoutLocalizationMiddleware();
    foreach (['/admin/bookshop', '/admin/bookshop/office/shops', '/admin/bookshop/office/money', '/admin/bookshop/office/reports'] as $href) {
        $web->actingAs(User::factory()->create())->get($href)->assertForbidden();
    }
    $web->actingAs(actingSystemAdmin(['bookshop.manage']))->get('/admin/bookshop/office/everything')->assertNotFound();
});
