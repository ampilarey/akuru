<?php

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * STATUS §5kv, from the owner's phone screenshot of /shop/fitrah: the filter
 * form filled the first screen, "In stock only" was a large empty square,
 * the dropdown arrows sat on their text, and a shop with nothing listed
 * said "Nothing matches your search yet."
 */
function phoneShop(string $slug = 'quiet-shop'): Vendor
{
    return Vendor::query()->create(['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active']);
}

function phoneShopPage(string $url): string
{
    return test()->withoutLocalizationMiddleware()->get($url)->assertOk()->getContent();
}

it('keeps the search in view and folds the rest under Filter and sort, counting what is in use', function () {
    phoneShop();

    $plain = phoneShopPage(route('public.shop.vendor', 'quiet-shop'));
    expect($plain)->toContain('data-testid="shop-search"')
        ->toContain('data-testid="shop-search-go"')
        ->toMatch('#<details class="shop-more mt-3" open data-active="0"#')
        ->toContain('Filter and sort')
        // Served open so it works without script; the script folds it on a phone.
        ->toContain("window.matchMedia('(min-width: 768px)')");

    $filtered = phoneShopPage(route('public.shop.vendor', ['vendor' => 'quiet-shop', 'in_stock' => 1, 'sort' => 'price_desc', 'language' => 'Arabic']));
    expect($filtered)->toContain('data-active="3"')
        ->toContain('<span class="shop-more-count">3</span>');
});

it('says a shop has nothing listed yet, and keeps "nothing matches" for a search', function () {
    phoneShop();

    expect(phoneShopPage(route('public.shop.vendor', 'quiet-shop')))->toContain('This shop has not listed any products yet.')
        ->not->toContain('Nothing matches your search yet.');
    expect(phoneShopPage(route('public.shop.vendor', ['vendor' => 'quiet-shop', 'q' => 'zzz'])))->toContain('Nothing matches your search yet.');
    expect(phoneShopPage(route('public.shop.index', ['q' => 'zzz'])))->toContain('Nothing matches your search yet.');
});

it('still filters and sorts from the folded form, and keeps the CSV', function () {
    $shop = phoneShop();
    foreach ([['Cheap', 50, 5], ['Dear', 300, 5], ['Gone', 200, 0]] as [$title, $price, $stock]) {
        Product::query()->create(['vendor_id' => $shop->id, 'slug' => strtolower($title), 'title' => $title, 'price' => $price, 'currency' => 'MVR',
            'tax_class' => 'standard', 'track_stock' => true, 'stock' => $stock, 'status' => 'active', 'visibility' => 'shop']);
    }

    $html = phoneShopPage(route('public.shop.vendor', ['vendor' => 'quiet-shop', 'in_stock' => 1, 'sort' => 'price_desc']));
    preg_match_all('/data-product="([^"]+)"/', $html, $m);
    expect(array_values(array_unique($m[1])))->toBe(['dear', 'cheap'])
        ->and($html)->toContain('data-testid="shop-export"');
});

it('lets a checkbox keep its size on a phone', function () {
    $layout = file_get_contents(resource_path('views/public/layouts/public.blade.php'));

    expect($layout)->toContain('input:not([type="checkbox"]):not([type="radio"])')
        ->and($layout)->not->toMatch('/^\s*button, a, input, select, textarea \{/m');
});
