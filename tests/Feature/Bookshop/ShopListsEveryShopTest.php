<?php

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * STATUS §5kw: the owner could not find their shops on /shop. The list
 * left out any shop with nothing on sale, and on production Fitrah had
 * nothing listed yet, so it was not there at all.
 */
function listedShop(string $slug, string $status = 'active'): Vendor
{
    return Vendor::query()->create(['name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'tagline' => $slug.' line', 'status' => $status]);
}

it('lists every open shop, the ones with products first, and says when one is opening soon', function () {
    $stocked = listedShop('zebra-books');
    listedShop('apple-crafts');
    listedShop('closed-shop', 'suspended');
    Product::query()->create(['vendor_id' => $stocked->id, 'slug' => 'a-book', 'title' => 'A Book', 'price' => 50, 'currency' => 'MVR',
        'tax_class' => 'standard', 'track_stock' => true, 'stock' => 3, 'status' => 'active', 'visibility' => 'shop']);

    $html = test()->withoutLocalizationMiddleware()->get(route('public.shop.index'))->assertOk()->getContent();

    preg_match('#data-testid="shop-vendors"(.*?)</section>#s', $html, $block);
    preg_match_all('#data-vendor="([^"]+)"#', $block[1], $order);
    $listed = array_values(array_diff($order[1], ['fitrah']));
    expect($listed)->toBe(['zebra-books', 'apple-crafts'])
        ->and($block[1])->toContain('1 items')
        ->and($block[1])->toContain('Opening soon')
        ->and($block[1])->toContain('href="'.route('public.shop.vendor', 'apple-crafts').'"')
        // A suspended shop is still nowhere.
        ->and($block[1])->not->toContain('closed-shop');
});

it('says opening soon in Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        expect(__('shop.opening_soon', [], $locale))->not->toBe('shop.opening_soon')->not->toBe('Opening soon');
    }
});
