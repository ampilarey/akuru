<?php

use App\Domains\Bookshop\Actions\CreateVendorAction;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\ProductImage;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN slice B1a, the vendor side: a member accepts the Vendor
 * Agreement, then lists products with photos, variants, stock, tax class
 * and book or educational details — and never touches another vendor's.
 */
function portalVendor(string $name, string $ownerEmail, bool $accepted = true): array
{
    Role::findOrCreate('vendor', 'web');
    $created = app(CreateVendorAction::class)->execute(['name' => $name, 'owner_name' => $name.' Owner', 'owner_email' => $ownerEmail], User::factory()->create()->id);
    if ($accepted) {
        VendorMember::query()->where('vendor_id', $created['vendor_id'])->update(['agreement_accepted_at' => now()]);
    }

    return [$created['vendor_id'], User::query()->findOrFail($created['owner_user_id'])];
}

function portal(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

function productInput(array $overrides = []): array
{
    return $overrides + [
        'title' => 'Arabic Letters Workbook',
        'price' => '120.00',
        'tax_class' => 'zero_rated',
        'status' => 'active',
        'visibility' => 'shop',
        'stock' => 25,
        'track_stock' => 1,
    ];
}

it('opens with the agreement, refuses writes until it is accepted, then dates the acceptance', function () {
    [$vendorId, $owner] = portalVendor('Fitrah', 'owner@example.test', accepted: false);

    portal($owner)->get(route('vendor.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Bookshop/Vendor')
            ->where('vendor.name', 'Fitrah')
            ->where('vendor.role', 'owner')
            ->where('vendor.agreement_accepted', false)
            ->where('products', [])
            ->where('must_set_password', true));

    portal($owner)->post(route('vendor.products.store'), productInput())->assertForbidden();
    portal($owner)->post(route('vendor.agreement'), ['accept' => false])->assertSessionHasErrors('accept');

    portal($owner)->post(route('vendor.agreement'), ['accept' => true])->assertRedirect(route('vendor.index'));
    expect(VendorMember::query()->where('vendor_id', $vendorId)->value('agreement_accepted_at'))->not->toBeNull();

    portal($owner)->post(route('vendor.products.store'), productInput())->assertSessionHasNoErrors();
    expect(Product::query()->where('vendor_id', $vendorId)->count())->toBe(1);
});

it('refuses the portal to someone who is not in a shop', function () {
    portal(User::factory()->create())->get(route('vendor.index'))->assertForbidden();
    portal(User::factory()->create())->post(route('vendor.products.store'), productInput())->assertForbidden();
});

it('saves a product with photos, variants, details, tags and a sanitised description', function () {
    Storage::fake('public');
    [$vendorId, $owner] = portalVendor('Fitrah', 'owner@example.test');
    $category = ProductCategory::query()->create(['name' => 'Workbooks', 'slug' => 'workbooks']);

    portal($owner)->post(route('vendor.products.store'), productInput([
        'product_category_id' => $category->id,
        'title_dv' => 'ޢަރަބި އަކުރުގެ ފޮތް',
        'summary' => 'Trace and learn the 28 letters.',
        'description' => "First paragraph.\n\nSecond <b>line</b> with <script>alert(1)</script>",
        'compare_at_price' => '150.00',
        'sku' => 'FIT-001',
        'barcode' => '9789990000001',
        'tags' => ['arabic', 'grade 1', 'arabic'],
        'details' => ['author' => 'Akuru Press', 'pages' => '64', 'age_range' => '5–7', 'unknown_key' => 'dropped'],
        'variants_sent' => 1,
        'variants' => [
            ['name' => 'Paperback', 'sku' => 'FIT-001-P', 'stock' => 10, 'is_active' => 1],
            ['name' => 'Spiral', 'price' => '140.00', 'stock' => 5, 'is_active' => 1],
        ],
        'photos' => [UploadedFile::fake()->image('front.jpg', 600, 600), UploadedFile::fake()->image('back.png', 600, 600)],
    ]))->assertSessionHasNoErrors();

    $product = Product::query()->sole();
    expect($product->vendor_id)->toBe($vendorId)
        ->and($product->slug)->toBe('arabic-letters-workbook')
        ->and($product->tax_class->value)->toBe('zero_rated')
        ->and((string) $product->price)->toBe('120.00')
        ->and($product->tags)->toBe(['arabic', 'grade 1'])
        ->and($product->details)->toBe(['author' => 'Akuru Press', 'pages' => '64', 'age_range' => '5–7'])
        ->and($product->description)->toContain('<p>First paragraph.</p>')
        ->and($product->description)->not->toContain('<script')
        ->and($product->description)->not->toContain('alert(1)')
        ->and($product->variants()->pluck('name')->all())->toBe(['Paperback', 'Spiral'])
        ->and((string) $product->variants()->where('name', 'Spiral')->value('price'))->toBe('140.00')
        ->and($product->images()->count())->toBe(2);

    portal($owner)->get(route('vendor.index'))
        ->assertInertia(fn ($page) => $page
            ->where('products.0.title', 'Arabic Letters Workbook')
            ->where('products.0.category', 'Workbooks')
            ->where('products.0.images.0.url', fn ($url) => is_string($url) && str_contains($url, 'shop-products/'))
            ->has('products.0.variants', 2));
});

it('keeps SKUs unique within a vendor only, and a "was" price above the price', function () {
    [, $a] = portalVendor('Fitrah', 'a@example.test');
    [, $b] = portalVendor('Other Shop', 'b@example.test');

    portal($a)->post(route('vendor.products.store'), productInput(['sku' => 'SKU-1']))->assertSessionHasNoErrors();
    portal($a)->post(route('vendor.products.store'), productInput(['title' => 'Second', 'sku' => 'SKU-1']))->assertSessionHasErrors('sku');
    portal($b)->post(route('vendor.products.store'), productInput(['sku' => 'SKU-1']))->assertSessionHasNoErrors();

    portal($a)->post(route('vendor.products.store'), productInput(['title' => 'Cheap', 'compare_at_price' => '100.00']))->assertSessionHasErrors('compare_at_price');
    expect(Product::query()->count())->toBe(2);
});

it('never lets one vendor see or touch another vendor\'s products or photos', function () {
    Storage::fake('public');
    [$aId, $a] = portalVendor('Fitrah', 'a@example.test');
    [$bId, $b] = portalVendor('Other Shop', 'b@example.test');

    portal($b)->post(route('vendor.products.store'), productInput([
        'title' => 'B Secret Product', 'photos' => [UploadedFile::fake()->image('b.jpg')],
    ]))->assertSessionHasNoErrors();
    $bProduct = Product::query()->where('vendor_id', $bId)->sole();
    $bImage = ProductImage::query()->sole();

    portal($a)->post(route('vendor.products.update', $bProduct->id), productInput(['title' => 'Hijacked']))->assertNotFound();
    portal($a)->post(route('vendor.product-images.arrange', $bImage->id), ['move' => 'remove'])->assertNotFound();
    expect($bProduct->refresh()->title)->toBe('B Secret Product')
        ->and(ProductImage::query()->count())->toBe(1);

    portal($a)->get(route('vendor.index'))->assertInertia(fn ($page) => $page->where('products', []));
    $csv = portal($a)->get(route('vendor.products.export'))->assertOk()->streamedContent();
    expect($csv)->not->toContain('B Secret Product');

    // The same rule the other way: B's own export has it.
    expect(portal($b)->get(route('vendor.products.export'))->streamedContent())->toContain('B Secret Product');
    expect($aId)->not->toBe($bId);
});

it('makes a photo first, removes one, and syncs variants away', function () {
    Storage::fake('public');
    [, $owner] = portalVendor('Fitrah', 'owner@example.test');

    portal($owner)->post(route('vendor.products.store'), productInput([
        'variants_sent' => 1,
        'variants' => [['name' => 'Small', 'stock' => 1], ['name' => 'Large', 'stock' => 2]],
        'photos' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg'), UploadedFile::fake()->image('three.jpg')],
    ]))->assertSessionHasNoErrors();
    $product = Product::query()->sole();
    [$one, $two, $three] = $product->images()->get()->all();

    portal($owner)->post(route('vendor.product-images.arrange', $three->id), ['move' => 'first'])->assertSessionHasNoErrors();
    expect($product->images()->pluck('id')->all())->toBe([$three->id, $one->id, $two->id]);

    portal($owner)->post(route('vendor.product-images.arrange', $one->id), ['move' => 'remove'])->assertSessionHasNoErrors();
    expect($product->images()->pluck('id')->all())->toBe([$three->id, $two->id]);

    // Editing with every variant removed: the form says it sent them.
    portal($owner)->post(route('vendor.products.update', $product->id), productInput(['variants_sent' => 1]))->assertSessionHasNoErrors();
    expect(ProductVariant::query()->count())->toBe(0)
        ->and($product->refresh()->slug)->toBe('arabic-letters-workbook');
});

it('lets the owner add staff with a one-time password, and not staff', function () {
    [$vendorId, $owner] = portalVendor('Fitrah', 'owner@example.test');

    $response = portal($owner)->post(route('vendor.members.store'), ['name' => 'Helper', 'email' => 'helper@example.test'])->assertSessionHasNoErrors();
    expect($response->getSession()->get('temporary_password'))->toBeString();

    $staff = User::query()->where('email', 'helper@example.test')->sole();
    expect(VendorMember::query()->where('user_id', $staff->id)->value('role')->value)->toBe('staff')
        ->and($staff->hasRole('vendor'))->toBeTrue();

    // Staff accept the agreement for themselves, then may list products but not add people.
    portal($staff)->post(route('vendor.products.store'), productInput())->assertForbidden();
    portal($staff)->post(route('vendor.agreement'), ['accept' => true]);
    portal($staff)->post(route('vendor.products.store'), productInput())->assertSessionHasNoErrors();
    portal($staff)->post(route('vendor.members.store'), ['name' => 'X', 'email' => 'x@example.test'])->assertForbidden();

    portal($owner)->post(route('vendor.members.store'), ['name' => 'Helper', 'email' => 'helper@example.test'])->assertSessionHasErrors('email');
    expect(VendorMember::query()->where('vendor_id', $vendorId)->count())->toBe(2);
});

it('switches between the shops a person belongs to, and only those', function () {
    [$aId, $person] = portalVendor('Fitrah', 'person@example.test');
    [$bId] = portalVendor('Other Shop', 'b@example.test');
    [$cId] = portalVendor('Third Shop', 'c@example.test');
    VendorMember::query()->create(['vendor_id' => $bId, 'user_id' => $person->id, 'role' => 'staff', 'agreement_accepted_at' => now()]);

    portal($person)->get(route('vendor.index'))->assertInertia(fn ($page) => $page->where('vendor.id', $aId)->has('memberships', 2));

    portal($person)->post(route('vendor.switch'), ['vendor_id' => $bId])->assertRedirect(route('vendor.index'));
    portal($person)->get(route('vendor.index'))->assertInertia(fn ($page) => $page->where('vendor.id', $bId)->where('vendor.role', 'staff'));

    portal($person)->post(route('vendor.switch'), ['vendor_id' => $cId])->assertForbidden();
});
