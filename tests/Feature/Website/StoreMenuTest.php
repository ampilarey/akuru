<?php

use App\Domains\Bookshop\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * STATUS §5ky: the owner could not find the shops' pages from the shop.
 * The Bookstore gets its own caret menu in the header (as the Digital
 * Library did in R5), a row of the same doors at the top of /shop, and the
 * footer links the shops and the shop owners' way in.
 */
function storePage(string $route = 'public.home', array $params = []): string
{
    Cache::flush();

    return test()->withoutLocalizationMiddleware()->get(route($route, $params))->assertOk()->getContent();
}

it('opens the Bookstore\'s sections from its caret in the header, and lists them under its row on a phone', function () {
    $html = storePage();

    preg_match('#data-testid="nav-bookstore-menu">(.*?)</div>#s', $html, $menu);
    expect($menu)->not->toBeEmpty()->and($html)->toContain('data-testid="nav-bookstore-more"');
    preg_match_all('#<a href="([^"]+)" data-testid="nav-bookstore-([a-z-]+)"#', $menu[1], $links, PREG_SET_ORDER);
    expect(collect($links)->mapWithKeys(fn ($l) => [$l[2] => html_entity_decode($l[1])])->all())->toBe([
        'all' => route('public.shop.index'),
        'shops' => route('public.shop.index').'#shops',
        'deals' => route('public.shop.deals'),
        'categories' => route('public.shop.index').'#categories',
        'my-orders' => route('public.shop.orders'),
        'sell' => route('vendor.apply'),
        'owners' => route('vendor.index'),
    ]);
    // The product link itself still opens the store.
    expect($html)->toMatch('#href="'.preg_quote(route('public.shop.index'), '#').'"\s+data-testid="nav-bookstore"#');

    preg_match('#data-testid="mobile-menu-bookstore">(.*?)</div>#s', $html, $phone);
    expect(substr_count($phone[1], '<a '))->toBe(6)
        ->and($phone[1])->toContain('href="'.route('public.shop.index').'#shops"');
});

it('puts the same doors at the top of the shop, with the shops listed where the link lands', function () {
    Vendor::query()->create(['name' => 'Quiet Shop', 'slug' => 'quiet-shop', 'code' => 'QUI', 'status' => 'active']);

    $home = storePage('public.shop.index');
    expect($home)->toContain('data-testid="shop-links"')
        ->toContain('href="#shops" class="')
        ->toContain('<section id="shops"')
        ->toContain('data-vendor="quiet-shop"')
        ->toContain('href="'.route('vendor.index').'"');

    // On a shop's own page the links lead back to the store's lists.
    expect(storePage('public.shop.vendor', ['vendor' => 'quiet-shop']))
        ->toContain('href="'.route('public.shop.index').'#shops"');
});

it('links the shops and the shop owners\' way in from the footer', function () {
    preg_match('#data-testid="footer-bookstore"(.*?)</details>#s', storePage(), $block);

    expect($block[1])->toContain('href="'.route('public.shop.index').'#shops"')
        ->toContain('href="'.route('vendor.index').'"')
        ->toContain('href="'.route('vendor.apply').'"');
});

it('names the Bookstore\'s sections in Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['store_all', 'shops', 'store_deals', 'shop_categories', 'my_orders', 'shop_owner_signin', 'store_menu'] as $key) {
            expect(__('site.'.$key, [], $locale))->not->toBe('site.'.$key)->not->toBe(__('site.'.$key, [], 'en'));
        }
    }
});
