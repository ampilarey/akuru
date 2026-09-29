<?php

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * STATUS §5kz, the owner's decision (2026-09-29): vendors asked for their
 * shop's page to carry their brand, so a shop's own pages keep the Akuru
 * header but end with only the copyright line. The rest of the site keeps
 * the full footer.
 */
function shopFooterVendor(): Vendor
{
    $shop = Vendor::query()->create(['name' => 'Quiet Shop', 'slug' => 'quiet-shop', 'code' => 'QUI', 'status' => 'active']);
    Product::query()->create(['vendor_id' => $shop->id, 'slug' => 'quiet-book', 'title' => 'Quiet Book', 'price' => 20, 'currency' => 'MVR',
        'tax_class' => 'standard', 'track_stock' => true, 'stock' => 2, 'status' => 'active', 'visibility' => 'shop']);

    return $shop;
}

function shopFooterPage(string $url): string
{
    Cache::flush();

    return test()->withoutLocalizationMiddleware()->get($url)->assertOk()->getContent();
}

it('ends a shop\'s own page with only the copyright line, and keeps the header', function () {
    shopFooterVendor();

    $html = shopFooterPage(route('public.shop.vendor', 'quiet-shop'));

    expect($html)->toContain('data-testid="footer-compact"')
        ->toContain('© '.date('Y').' Akuru Institute. All rights reserved')
        ->not->toContain('data-footer-group')
        ->not->toContain('data-testid="footer-about"')
        // The header stays, on a desk and on a phone.
        ->toContain('data-testid="site-nav"')
        ->toContain('data-testid="nav-bookstore-more"')
        ->toContain('data-testid="mobile-menu"')
        // No bar is fixed to the foot of a phone (the owner's screenshot, §5kz):
        // neither the site's nor the store's.
        ->not->toContain('data-testid="bottom-bar"')
        ->not->toContain('data-testid="shop-bottom-bar"')
        // The cart is one tap away in the store's links instead.
        ->toContain('data-testid="shop-link-cart"')
        ->toContain('href="'.route('public.shop.cart').'"');
});

it('keeps the full Akuru footer and the phone bar everywhere else, the shop\'s front and product pages included', function () {
    shopFooterVendor();

    foreach ([route('public.home'), route('public.shop.index'), route('public.shop.product', 'quiet-book'), route('public.library.index')] as $url) {
        expect(shopFooterPage($url))->toContain('data-footer-group')->not->toContain('data-testid="footer-compact"')
            ->toContain('data-testid="bottom-bar"');
    }
});

it('says the copyright line in Dhivehi and Arabic too', function () {
    shopFooterVendor();

    foreach (['dv', 'ar'] as $locale) {
        app()->setLocale($locale);
        expect(shopFooterPage(route('public.shop.vendor', 'quiet-shop')))->toContain(__('site.rights_reserved', [], $locale));
    }
});
