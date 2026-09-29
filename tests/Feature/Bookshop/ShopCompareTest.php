<?php

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * STATUS §5li, compare products: up to four, kept in this device's session;
 * a table shows only the rows any of them fills; a product no longer for
 * sale drops out.
 */
function compareProduct(Vendor $vendor, string $title, float $price, array $details = [], array $overrides = []): Product
{
    return Product::query()->create($overrides + [
        'vendor_id' => $vendor->id, 'slug' => Str::slug($title), 'title' => $title, 'price' => $price, 'details' => $details,
        'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => 5, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

function compareVisitor()
{
    return test()->withoutLocalizationMiddleware();
}

it('adds products to a comparison and shows them side by side, only the rows any of them fills', function () {
    $shop = Vendor::query()->create(['name' => 'Compare Shop', 'slug' => 'compare-shop', 'code' => 'CMP', 'status' => 'active']);
    $a = compareProduct($shop, 'Atlas One', 100, ['author' => 'A. Writer', 'pages' => '120']);
    $b = compareProduct($shop, 'Atlas Two', 150, ['pages' => '200']);

    compareVisitor()->get(route('public.shop.compare'))->assertOk()->assertSee(__('shop.compare_empty'));

    compareVisitor()->post(route('public.shop.compare.toggle', $a->slug))->assertSessionHas('success', __('shop.compare_added_flash'));
    compareVisitor()->post(route('public.shop.compare.toggle', $b->slug));
    compareVisitor()->get(route('public.shop.product', $a->slug))->assertSee('aria-pressed="true" data-testid="compare-toggle"', false)->assertSee('data-testid="go-compare"', false);

    $page = compareVisitor()->get(route('public.shop.compare'))->assertOk()
        ->assertSeeInOrder(['Atlas One', 'Atlas Two'])
        ->assertSee('data-compare-row="price"', false)->assertSee('MVR 100.00')->assertSee('MVR 150.00')
        ->assertSee('data-compare-row="author"', false)->assertSee('A. Writer')
        ->assertSee('data-compare-row="pages"', false)
        ->assertDontSee('data-compare-row="publisher"', false)
        ->assertDontSee('data-testid="shop-filters"', false)
        ->assertSee('data-testid="compare-add-atlas-one"', false)
        ->assertSee('data-testid="shop-link-compare"', false);
    expect($page->getContent())->toContain('<td class="p-3" dir="auto">—</td>');

    // Taken out again.
    compareVisitor()->post(route('public.shop.compare.toggle', $a->slug))->assertSessionHas('success', __('shop.compare_removed_flash'));
    compareVisitor()->get(route('public.shop.compare'))->assertDontSee('data-compare-product="atlas-one"', false)->assertSee('data-compare-product="atlas-two"', false);
});

it('holds four at most, and drops a product that is no longer for sale', function () {
    $shop = Vendor::query()->create(['name' => 'Compare Shop', 'slug' => 'compare-shop', 'code' => 'CMP', 'status' => 'active']);
    $products = collect(range(1, 5))->map(fn (int $n) => compareProduct($shop, "Book {$n}", 10 * $n));

    foreach ($products->take(4) as $p) {
        compareVisitor()->post(route('public.shop.compare.toggle', $p->slug))->assertSessionHasNoErrors();
    }
    compareVisitor()->post(route('public.shop.compare.toggle', $products[4]->slug))->assertSessionHasErrors('compare');

    $products[0]->update(['status' => 'archived']);
    compareVisitor()->get(route('public.shop.compare'))->assertOk()
        ->assertDontSee('data-compare-product="book-1"', false)->assertSee('data-compare-product="book-4"', false);
    compareVisitor()->post(route('public.shop.compare.toggle', 'nope'))->assertNotFound();
});
