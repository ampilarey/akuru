<?php

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\ShopHomeFeature;
use App\Domains\Bookshop\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * STATUS §5lu, the owner (2026-09-29): "enhance the layout of the bookstore
 * and each vendor page … take ideas from iruali". The store's front opens on a
 * hero card with two tiles, categories as tiles, shelves as rows; a shop's page
 * on a card with its name; the listings on a strip of category chips; a card
 * shows a sale as a percentage.
 */
function layoutSetup(): Vendor
{
    $books = ProductCategory::query()->create(['name' => 'Islamic studies', 'slug' => 'islamic-studies', 'is_active' => true, 'sort_order' => 1]);
    $toys = ProductCategory::query()->create(['name' => 'Toys and games', 'slug' => 'toys-games', 'is_active' => true, 'sort_order' => 2]);
    $shop = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active', 'tagline' => 'Books for young Muslims']);
    $other = Vendor::query()->create(['name' => 'Other', 'slug' => 'other', 'code' => 'OTH', 'status' => 'active']);
    $make = fn (Vendor $v, string $slug, ProductCategory $c, array $extra = []) => Product::query()->create($extra + [
        'vendor_id' => $v->id, 'slug' => $slug, 'title' => ucfirst($slug), 'price' => 100, 'currency' => 'MVR', 'product_category_id' => $c->id,
        'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop',
    ]);
    $make($shop, 'seerah-book', $books, ['sale_percent' => 25, 'sale_ends_at' => now()->addDay()]);
    $make($shop, 'dua-cards', $books);
    $make($other, 'puzzle', $toys);

    return $shop;
}

function layoutPage(string $url): string
{
    Cache::flush();

    return test()->withoutLocalizationMiddleware()->get($url)->assertOk()->getContent();
}

it('opens the store on a hero card, two tiles, category tiles and shelves that swipe', function () {
    layoutSetup();
    $html = layoutPage(route('public.shop.index'));

    expect($html)->toContain('data-testid="store-hero"')->toContain('data-testid="shop-heading">Akuru Bookstore<')
        ->toContain(__('shop.hero_badge'))->toContain('data-testid="store-hero-shop"')->toContain('href="#categories"')
        ->toContain('data-testid="tile-deals"')->toContain('href="'.route('public.shop.deals').'"')
        ->toContain('data-testid="tile-book-lists"')
        // Categories as tiles, each with its count.
        ->toMatch('#data-testid="shop-categories".*'.preg_quote(route('public.shop.category', 'islamic-studies'), '#').'.*'.preg_quote(__('shop.result_count', ['count' => 2]), '#').'#s')
        // Shelves are rows, with a way to all of them.
        ->toContain('class="shop-row shop-scroll"')->toContain(e(route('public.shop.index', ['sort' => 'newest'])).'#shop-grid')
        // The filters fold on the front, at every width; the search stays.
        ->toContain('class="shop-more mt-3 shop-more-front"')->toContain('data-testid="shop-search"')
        // The front has tiles, not the strip of chips.
        ->not->toContain('data-testid="category-chips">');
});

it('gives the office\'s hero slides the big card, the page still named', function () {
    layoutSetup();
    ShopHomeFeature::query()->create(['kind' => 'hero', 'heading' => 'Back to school', 'subheading' => 'Lists ready', 'link' => ['kind' => 'deals'], 'sort_order' => 1, 'is_active' => true]);

    $html = layoutPage(route('public.shop.index'));
    expect($html)->toContain('data-testid="shop-hero"')->toContain('Back to school')->not->toContain('data-testid="store-hero"')
        ->toContain('<h1 class="sr-only" data-testid="shop-heading">Akuru Bookstore</h1>')
        ->toContain('data-testid="tile-deals"');
});

it('puts a shop\'s page on a card with its name, and its own categories as chips', function () {
    layoutSetup();
    $html = layoutPage(route('public.shop.vendor', 'fitrah'));

    expect($html)->toContain('data-testid="shop-head"')->toContain('data-testid="shop-heading" dir="auto">Fitrah<')
        ->toContain('Books for young Muslims')->toContain('data-testid="at-akuru"')
        ->toContain('data-testid="shop-head-count">'.__('shop.result_count', ['count' => 2]))
        ->toContain('data-testid="category-chips"')
        ->toContain(e(route('public.shop.vendor', ['vendor' => 'fitrah', 'category' => 'islamic-studies'])))
        // Only its own categories.
        ->not->toContain('data-chip="toys-games"')
        // Nothing narrowed: "All products", and "All categories" is the chosen chip.
        ->toContain(__('shop.all_products'))->toMatch('#shop-chip is-active">'.preg_quote(__('shop.all_categories'), '#').'#');

    // A chosen category is the chosen chip, and the count leaves the head (it would be the narrowed count).
    $narrowed = layoutPage(route('public.shop.vendor', ['vendor' => 'fitrah', 'category' => 'islamic-studies']));
    expect($narrowed)->toContain('class="shop-chip is-active" dir="auto" data-chip="islamic-studies"')->not->toContain('data-testid="shop-head-count"');
});

it('lays the store\'s category page on a heading and a strip of every category, its own chosen', function () {
    layoutSetup();
    $html = layoutPage(route('public.shop.category', 'toys-games'));

    expect($html)->toContain('data-testid="shop-heading" dir="auto">Toys and games<')
        ->toContain('data-chip="islamic-studies"')->toContain('class="shop-chip is-active" dir="auto" data-chip="toys-games"')
        ->not->toContain('data-testid="store-hero"')->not->toContain('data-testid="shop-head"');
});

it('shows a running sale on the card as a percentage off', function () {
    layoutSetup();

    expect(layoutPage(route('public.shop.vendor', 'fitrah')))->toMatch('#data-product="seerah-book".*?data-badge="percent-off">&minus;25%<#s')
        ->not->toMatch('#data-product="dua-cards"[^>]*>(?:(?!data-product=).)*data-badge="percent-off"#s');
});

it('keeps the store\'s links to one strip that swipes on a phone', function () {
    layoutSetup();

    expect(layoutPage(route('public.shop.vendor', 'fitrah')))->toContain('class="shop-scroll container mx-auto flex gap-2 overflow-x-auto');
});

it('reads right to left in Dhivehi, with the new words', function () {
    layoutSetup();
    app()->setLocale('dv');

    expect(layoutPage(route('public.shop.index')))->toContain(__('shop.browse_categories', [], 'dv'))->toContain(__('shop.tile_deals_sub', [], 'dv'))->toContain('dir="rtl"');
});
