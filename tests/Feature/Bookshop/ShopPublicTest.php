<?php

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\ProductImage;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Media\Actions\StorePublicMediaAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN slice B1b: the public Akuru Bookstore — one catalogue
 * of every vendor's products for sale, a category, a search, a product
 * page and a vendor's plain page; and never a draft, an archived product or
 * a suspended vendor's.
 */
function shopVendor(string $slug, string $status = 'active'): Vendor
{
    return Vendor::query()->create(['name' => ucfirst(str_replace('-', ' ', $slug)), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'tagline' => $slug.' tagline', 'status' => $status]);
}

function shopProduct(Vendor $vendor, string $title, array $overrides = []): Product
{
    return Product::query()->create($overrides + [
        'vendor_id' => $vendor->id,
        'slug' => \Illuminate\Support\Str::slug($title),
        'title' => $title,
        'price' => 100,
        'currency' => 'MVR',
        'tax_class' => 'standard',
        'track_stock' => true,
        'stock' => 10,
        'status' => 'active',
        'visibility' => 'shop',
    ]);
}

function shopGet(string $url)
{
    return test()->withoutLocalizationMiddleware()->get($url);
}

function shopOrder($response): array
{
    preg_match_all('/data-product="([^"]+)"/', $response->getContent(), $m);

    return array_values(array_unique($m[1]));
}

it('shows every vendor\'s products for sale in one shop, and nothing that is not for sale', function () {
    $fitrah = shopVendor('fitrah');
    $other = shopVendor('other-shop');
    $gone = shopVendor('gone-shop', 'suspended');

    shopProduct($fitrah, 'Arabic Workbook');
    shopProduct($other, 'Wooden Puzzle');
    shopProduct($fitrah, 'Draft Thing', ['status' => 'draft']);
    shopProduct($fitrah, 'Old Thing', ['status' => 'archived']);
    shopProduct($gone, 'Suspended Thing');
    shopProduct($fitrah, 'Page Only', ['visibility' => 'storefront']);

    $home = shopGet(route('public.shop.index'))->assertOk()
        ->assertSee('Akuru Bookstore')
        ->assertSee('Sold by')
        ->assertSee('data-testid="new-arrivals"', false)
        ->assertSee('data-testid="shop-bottom-bar"', false);
    expect(shopOrder($home))->toEqualCanonicalizing(['arabic-workbook', 'wooden-puzzle']);

    // A vendor's own page also carries what it keeps for its page alone.
    $page = shopGet(route('public.shop.vendor', 'fitrah'))->assertOk()
        ->assertSee('at Akuru Bookstore')
        ->assertSee('fitrah tagline');
    expect(shopOrder($page))->toEqualCanonicalizing(['arabic-workbook', 'page-only']);

    shopGet(route('public.shop.product', 'page-only'))->assertOk();
    foreach (['draft-thing', 'old-thing', 'suspended-thing'] as $slug) {
        shopGet(route('public.shop.product', $slug))->assertNotFound();
    }
    shopGet(route('public.shop.vendor', 'gone-shop'))->assertNotFound();
    shopGet(route('public.shop.vendor', 'no-such-shop'))->assertNotFound();
});

it('filters by category, price, stock, language and search, and sorts', function () {
    $fitrah = shopVendor('fitrah');
    $books = ProductCategory::query()->create(['name' => 'Books', 'slug' => 'books', 'name_dv' => 'ފޮތް']);
    $quran = ProductCategory::query()->create(['name' => 'Quran', 'slug' => 'quran', 'parent_id' => $books->id]);
    $toys = ProductCategory::query()->create(['name' => 'Toys', 'slug' => 'toys']);

    shopProduct($fitrah, 'Cheap Qaida', ['price' => 40, 'product_category_id' => $quran->id, 'details' => ['language' => 'Arabic'], 'tags' => ['tajweed']]);
    shopProduct($fitrah, 'Dear Atlas', ['price' => 400, 'product_category_id' => $books->id, 'details' => ['language' => 'English'], 'sku' => 'ATL-9']);
    shopProduct($fitrah, 'Sold Out Blocks', ['price' => 150, 'product_category_id' => $toys->id, 'stock' => 0, 'title_dv' => 'ބްލޮކް']);
    $made = shopProduct($fitrah, 'Made Mat', ['price' => 90, 'stock' => 0, 'lead_days' => 5]);

    // A category includes the categories inside it.
    expect(shopOrder(shopGet(route('public.shop.category', 'books'))->assertOk()->assertSee('Books')))->toEqualCanonicalizing(['cheap-qaida', 'dear-atlas']);
    shopGet(route('public.shop.category', 'no-such'))->assertNotFound();

    $list = fn (array $query) => shopOrder(shopGet(route('public.shop.index', $query))->assertOk());
    expect($list(['price_min' => 100, 'sort' => 'price_asc']))->toBe(['sold-out-blocks', 'dear-atlas'])
        ->and($list(['price_max' => 100, 'sort' => 'price_desc']))->toBe(['made-mat', 'cheap-qaida'])
        ->and($list(['in_stock' => 1, 'sort' => 'name']))->toBe(['cheap-qaida', 'dear-atlas', 'made-mat'])
        ->and($list(['language' => 'Arabic']))->toBe(['cheap-qaida'])
        ->and($list(['q' => 'tajweed']))->toBe(['cheap-qaida'])
        ->and($list(['q' => 'ATL-9']))->toBe(['dear-atlas'])
        ->and($list(['q' => 'ބްލޮކް']))->toBe(['sold-out-blocks'])
        ->and($list(['q' => 'fitrah', 'sort' => 'name']))->toBe(['cheap-qaida', 'dear-atlas', 'made-mat', 'sold-out-blocks']);
    expect($made->refresh()->lead_days)->toBe(5);
});

it('refuses a sort it does not know rather than guessing', function () {
    shopGet(route('public.shop.index', ['sort' => 'nonsense']))->assertRedirect();
});

it('shows a product page with its gallery, variants, stock, details and a sanitised description', function () {
    Storage::fake('public');
    $fitrah = shopVendor('fitrah');
    $category = ProductCategory::query()->create(['name' => 'Workbooks', 'slug' => 'workbooks']);
    $product = shopProduct($fitrah, 'Letters Book', [
        'product_category_id' => $category->id,
        'price' => 120, 'compare_at_price' => 150, 'stock' => 2, 'low_stock_at' => 3,
        'summary' => 'Trace the letters.',
        'description' => '<p>Big <strong>clear</strong> letters.</p>',
        'details' => ['author' => 'Akuru Press', 'pages' => '64', 'age_range' => '4–6'],
        'barcode' => '9789990000001',
    ]);
    ProductVariant::query()->create(['product_id' => $product->id, 'name' => 'Paperback', 'stock' => 2, 'is_active' => true]);
    ProductVariant::query()->create(['product_id' => $product->id, 'name' => 'Spiral', 'price' => 140, 'stock' => 0, 'is_active' => true]);
    $stored = app(StorePublicMediaAction::class)->execute(UploadedFile::fake()->image('front.jpg', 2000, 2000), null, ['image/jpeg'], [], 'shop-products');
    ProductImage::query()->create(['product_id' => $product->id, 'media_file_id' => $stored['id'], 'sort_order' => 0]);
    shopProduct($fitrah, 'Other Workbook', ['product_category_id' => $category->id]);

    $page = shopGet(route('public.shop.product', 'letters-book'))->assertOk()
        ->assertSee('Letters Book')
        ->assertSee('MVR 120.00')
        ->assertSee('150.00')
        ->assertSee('Only 2 left')
        ->assertSee('Paperback')
        ->assertSee('MVR 140.00')
        ->assertSee('Unavailable')
        ->assertSee('Akuru Press')
        ->assertSee('9789990000001')
        ->assertSee('<strong>clear</strong>', false)
        ->assertSee('Prices include any tax.')
        ->assertSee('data-testid="add-to-cart"', false)
        ->assertSee('data-product="other-workbook"', false)
        ->assertSee(route('public.shop.vendor', 'fitrah'), false);

    // The photo is served as resized WebP copies, not the 2000px original:
    // large on its own page, card-size in a listing.
    expect($page->getContent())->toContain('-w1200.webp');
    expect(shopGet(route('public.shop.index'))->getContent())->toContain('-w480.webp');
    $files = Storage::disk('public')->allFiles('shop-products');
    $large = collect($files)->first(fn ($f) => str_ends_with($f, '-w1200.webp'));
    expect($large)->not->toBeNull()
        ->and(getimagesize(Storage::disk('public')->path($large))[0])->toBe(1200);
});

it('speaks the visitor\'s language where the vendor gave one', function () {
    $fitrah = shopVendor('fitrah');
    shopProduct($fitrah, 'Prayer Mat', ['title_dv' => 'ނަމާދު ކުރާ ފޮތި', 'title_ar' => 'سجادة صلاة']);
    shopProduct($fitrah, 'English Only');

    App::setLocale('dv');
    shopGet(route('public.shop.index'))->assertOk()->assertSee('ނަމާދު ކުރާ ފޮތި')->assertSee('English Only')->assertSee('އަކުރު ފޮތްފިހާރަ');
    App::setLocale('ar');
    shopGet(route('public.shop.product', 'prayer-mat'))->assertOk()->assertSee('سجادة صلاة')->assertSee('في متجر أكورو للكتب');
});

it('exports the listing as it is filtered, and lists the shop in the sitemap', function () {
    $fitrah = shopVendor('fitrah');
    $books = ProductCategory::query()->create(['name' => 'Books', 'slug' => 'books']);
    shopProduct($fitrah, 'Atlas', ['product_category_id' => $books->id]);
    shopProduct($fitrah, 'Puzzle');
    shopProduct($fitrah, 'Hidden Draft', ['status' => 'draft']);

    $csv = shopGet(route('public.shop.export', ['category' => 'books']))->assertOk()->streamedContent();
    expect($csv)->toContain('Atlas')->not->toContain('Puzzle')->not->toContain('Hidden Draft');

    $sitemap = shopGet(route('public.sitemap'))->assertOk()->getContent();
    expect($sitemap)->toContain('/shop/products/atlas')
        ->toContain('/shop/products/puzzle')
        ->toContain('/shop/c/books')
        ->toContain('/shop/fitrah')
        ->not->toContain('hidden-draft');
});

it('never lets a vendor be named like one of the shop\'s own addresses', function () {
    \Spatie\Permission\Models\Role::findOrCreate('vendor', 'web');
    $created = app(\App\Domains\Bookshop\Actions\CreateVendorAction::class)->execute(
        ['name' => 'Products', 'owner_name' => 'X', 'owner_email' => 'x@example.test'],
        \App\Domains\Identity\Models\User::factory()->create()->id,
    );

    expect($created['slug'])->toBe('products-shop');
    shopGet('/shop/products-shop')->assertOk();
});
