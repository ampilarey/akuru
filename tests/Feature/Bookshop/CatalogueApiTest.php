<?php

use App\Domains\Bookshop\Models\Brand;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * STATUS §5ll, the Bookstore's public catalogue API: what /shop shows, as
 * JSON — products with the listing's filters, one product, the shops and
 * the categories. Nothing not for sale, and no field the pages keep back.
 */
function apiProduct(Vendor $vendor, string $title, float $price, array $overrides = []): Product
{
    return Product::query()->create($overrides + [
        'vendor_id' => $vendor->id, 'slug' => Str::slug($title), 'title' => $title, 'price' => $price,
        'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => 7, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

function apiCatalogue(): array
{
    $shop = Vendor::query()->create(['name' => 'Api Shop', 'slug' => 'api-shop', 'code' => 'API', 'status' => 'active', 'tagline' => 'Books for school', 'email' => 'owner-private@example.com']);
    $closed = Vendor::query()->create(['name' => 'Gone Shop', 'slug' => 'gone-shop', 'code' => 'GON', 'status' => 'suspended']);
    $books = ProductCategory::query()->create(['name' => 'Books', 'name_dv' => 'ފޮތް', 'slug' => 'books', 'is_active' => true]);
    ProductCategory::query()->create(['name' => 'Workbooks', 'slug' => 'workbooks', 'parent_id' => $books->id, 'is_active' => true]);
    $brand = Brand::query()->create(['name' => 'Crayola', 'slug' => 'crayola', 'is_active' => true]);

    $atlas = apiProduct($shop, 'School Atlas', 120, ['product_category_id' => $books->id, 'brand_id' => $brand->id, 'title_dv' => 'ސްކޫލް އެޓްލަސް', 'sku' => 'AT-1', 'details' => ['pages' => '64'], 'description' => 'A full atlas.']);
    ProductVariant::query()->create(['product_id' => $atlas->id, 'name' => 'Hardback', 'price' => 150, 'stock' => 2, 'is_active' => true]);
    apiProduct($shop, 'Maths Workbook', 50, ['product_category_id' => $books->id]);
    apiProduct($shop, 'Draft Book', 10, ['status' => 'draft']);
    apiProduct($shop, 'Storefront Only', 15, ['visibility' => 'storefront']);
    apiProduct($closed, 'Suspended Shop Book', 20);

    return [$shop, $atlas];
}

it('lists what is for sale, filtered and paged, with the page\'s address and nothing held back', function () {
    apiCatalogue();

    $response = $this->getJson(route('api.bookstore.products', ['sort' => 'price_asc']))->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.title', 'Maths Workbook')->assertJsonPath('data.1.title', 'School Atlas')
        ->assertJsonPath('data.1.shop', ['name' => 'Api Shop', 'slug' => 'api-shop'])
        ->assertJsonPath('data.1.availability', 'few_left')->assertJsonPath('data.0.availability', 'in_stock')
        ->assertJsonPath('data.1.url', url('en/shop/products/school-atlas'));
    expect(array_keys($response->json('data.1')))->toBe(['slug', 'title', 'summary', 'price', 'was_price', 'currency', 'sale_ends_at', 'availability', 'image', 'image_alt', 'shop', 'category', 'rating', 'url'])
        ->and($response->getContent())->not->toContain('Draft Book')->not->toContain('Storefront Only')->not->toContain('Suspended Shop Book')
        ->not->toContain('owner-private');

    $this->getJson(route('api.bookstore.products', ['q' => 'atlas']))->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.slug', 'school-atlas');
    $this->getJson(route('api.bookstore.products', ['shop' => 'gone-shop']))->assertJsonPath('meta.total', 0);
    $this->getJson(route('api.bookstore.products', ['brand' => 'crayola', 'price_max' => 200]))->assertJsonPath('meta.total', 1);
    $this->getJson(route('api.bookstore.products', ['per_page' => 1]))->assertJsonPath('meta.last_page', 2)->assertJsonCount(1, 'data')
        ->assertJsonPath('links.next', fn ($next) => str_contains((string) $next, 'page=2'));
    $this->getJson(route('api.bookstore.products', ['per_page' => 500]))->assertStatus(422);
    $this->getJson(route('api.bookstore.products', ['sort' => 'cost']))->assertStatus(422);
});

it('shows one product in the asked language, and a 404 for anything not for sale', function () {
    apiCatalogue();

    $one = $this->getJson(route('api.bookstore.product', ['slug' => 'school-atlas', 'lang' => 'dv']))->assertOk()
        ->assertJsonPath('data.title', 'ސްކޫލް އެޓްލަސް')
        ->assertJsonPath('data.brand', ['name' => 'Crayola', 'slug' => 'crayola'])
        ->assertJsonPath('data.sku', 'AT-1')->assertJsonPath('data.details.pages', '64')
        ->assertJsonPath('data.variants.0', ['name' => 'Hardback', 'price' => '150.00', 'in_stock' => true])
        ->assertJsonPath('data.url', url('dv/shop/products/school-atlas'))
        ->json('data');
    expect($one)->not->toHaveKey('id')->not->toHaveKey('tax_class')->not->toHaveKey('weight_grams')->not->toHaveKey('json_ld')->not->toHaveKey('related');

    $this->getJson(route('api.bookstore.product', 'draft-book'))->assertNotFound();
    $this->getJson(route('api.bookstore.product', 'suspended-shop-book'))->assertNotFound();
    $this->getJson(route('api.bookstore.product', 'nope'))->assertNotFound();
});

it('lists the open shops with their counts, and the categories with their parents', function () {
    apiCatalogue();

    $this->getJson(route('api.bookstore.shops'))->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0', ['name' => 'Api Shop', 'slug' => 'api-shop', 'tagline' => 'Books for school', 'products' => 2, 'url' => url('en/shop/api-shop')]);

    $this->getJson(route('api.bookstore.categories', ['lang' => 'dv']))->assertOk()
        ->assertJsonFragment(['name' => 'ފޮތް', 'slug' => 'books', 'parent' => null, 'url' => url('dv/shop/c/books')])
        ->assertJsonFragment(['name' => 'Workbooks', 'slug' => 'workbooks', 'parent' => 'books']);
});

it('is throttled at sixty a minute and never writes', function () {
    apiCatalogue();
    foreach (range(1, 60) as $n) {
        $this->getJson(route('api.bookstore.shops'))->assertOk();
    }
    $this->getJson(route('api.bookstore.shops'))->assertStatus(429);
    $this->postJson(route('api.bookstore.products'))->assertStatus(405);
});
