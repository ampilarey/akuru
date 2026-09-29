<?php

use App\Domains\Bookshop\Models\Brand;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * STATUS §5lh, brand pages: a brand's page lists its products from every
 * shop; the listing filters by brand; the store's front lists the brands
 * with something for sale; a product page links its brand.
 */
function brandProduct(Vendor $vendor, string $title, ?Brand $brand, array $overrides = []): Product
{
    return Product::query()->create($overrides + [
        'vendor_id' => $vendor->id, 'brand_id' => $brand?->id, 'slug' => Str::slug($title), 'title' => $title, 'price' => 40,
        'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

function brandVisitor()
{
    return test()->withoutLocalizationMiddleware();
}

it('shows a brand\'s products from every shop on its page, and filters the listing by brand', function () {
    $one = Vendor::query()->create(['name' => 'Shop One', 'slug' => 'shop-one', 'code' => 'ONE', 'status' => 'active']);
    $two = Vendor::query()->create(['name' => 'Shop Two', 'slug' => 'shop-two', 'code' => 'TWO', 'status' => 'active']);
    $crayola = Brand::query()->create(['name' => 'Crayola', 'slug' => 'crayola', 'is_active' => true]);
    $other = Brand::query()->create(['name' => 'Faber', 'slug' => 'faber', 'is_active' => true]);
    brandProduct($one, 'Crayola Crayons 24', $crayola);
    brandProduct($two, 'Crayola Markers', $crayola);
    brandProduct($one, 'Faber Pencils', $other);
    brandProduct($one, 'Crayola Draft', $crayola, ['status' => 'draft']);

    brandVisitor()->get(route('public.shop.brand', 'crayola'))->assertOk()
        ->assertSee('Crayola Crayons 24')->assertSee('Crayola Markers')
        ->assertDontSee('Faber Pencils')->assertDontSee('Crayola Draft')
        ->assertSee(__('shop.result_count', ['count' => 2]))
        ->assertDontSee('data-testid="filter-brand"', false);
    brandVisitor()->get(route('public.shop.brand', 'nope'))->assertNotFound();

    brandVisitor()->get(route('public.shop.index', ['brand' => 'faber']))->assertOk()
        ->assertSee('Faber Pencils')->assertDontSee('Crayola Markers')
        ->assertSee('data-testid="filter-brand"', false)->assertSee('<option value="faber" selected', false);
});

it('lists the brands with something for sale on the store\'s front, and links a product\'s brand', function () {
    $shop = Vendor::query()->create(['name' => 'Shop One', 'slug' => 'shop-one', 'code' => 'ONE', 'status' => 'active']);
    $crayola = Brand::query()->create(['name' => 'Crayola', 'slug' => 'crayola', 'is_active' => true]);
    Brand::query()->create(['name' => 'Empty Brand', 'slug' => 'empty-brand', 'is_active' => true]);
    $retired = Brand::query()->create(['name' => 'Retired Brand', 'slug' => 'retired-brand', 'is_active' => false]);
    $crayons = brandProduct($shop, 'Crayola Crayons 24', $crayola);
    $old = brandProduct($shop, 'Old Brand Thing', $retired);

    brandVisitor()->get(route('public.shop.index'))->assertOk()
        ->assertSee('data-testid="shop-brands"', false)->assertSee('data-brand="crayola"', false)
        ->assertDontSee('data-brand="empty-brand"', false)->assertDontSee('data-brand="retired-brand"', false);

    brandVisitor()->get(route('public.shop.product', $crayons->slug))->assertOk()
        ->assertSee('href="'.route('public.shop.brand', 'crayola').'"', false);
    brandVisitor()->get(route('public.shop.product', $old->slug))->assertOk()
        ->assertSee('Retired Brand')->assertDontSee('data-testid="product-brand-link"', false);
    brandVisitor()->get(route('public.shop.brand', 'retired-brand'))->assertNotFound();
});
