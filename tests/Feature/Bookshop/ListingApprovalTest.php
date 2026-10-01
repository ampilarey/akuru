<?php

use App\Domains\Bookshop\Enums\VendorMemberRole;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * COMMERCE_PARITY_PLAN P4: the office approves every listing. *Put on sale*
 * asks the office; approved, it is on sale; declined, it is back in draft
 * with the office's note. A live product goes back to the queue when what it
 * *is* changes (title, description, category, photos, variant names — D4),
 * not when its price, sale or stock does. A trusted shop skips the queue.
 */
beforeEach(function () {
    Storage::fake('public');
});

function listingShop(array $overrides = []): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create($overrides + ['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active']);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => VendorMemberRole::Owner->value, 'agreement_accepted_at' => now()]);

    return [$vendor, $owner];
}

function listingWeb()
{
    return test()->withoutLocalizationMiddleware();
}

function listingInput(array $overrides = []): array
{
    return $overrides + ['title' => 'Seerah for children', 'price' => 120, 'tax_class' => 'zero_rated', 'track_stock' => 1, 'stock' => 5, 'status' => 'active', 'visibility' => 'shop'];
}

it('asks the office before a product goes on sale, and puts it on sale once approved', function () {
    [$vendor, $owner] = listingShop();
    $office = actingSystemAdmin(['bookshop.manage']);

    listingWeb()->actingAs($owner)->post(route('vendor.products.store'), listingInput())->assertSessionHasNoErrors();
    $product = Product::query()->where('vendor_id', $vendor->id)->sole();
    expect($product->status->value)->toBe('pending_review')->and($product->submitted_at)->not->toBeNull();

    // Not in the store while it waits.
    listingWeb()->get(route('public.shop.product', $product->slug))->assertNotFound();

    // The office is told, and sees it in the queue.
    expect(UserNotification::query()->where('user_id', $office->id)->where('title', __('shop.notice_listing_submitted_title'))->exists())->toBeTrue();
    listingWeb()->actingAs($office)->get(route('admin.bookshop.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('listings', 1)->where('listings.0.title', 'Seerah for children')->where('listings.0.vendor', 'Fitrah')->where('listings.0.changes', null));

    // The shop cannot approve its own.
    listingWeb()->actingAs($owner)->post(route('admin.bookshop.listings.decide', $product->id), ['decision' => 'approve'])->assertForbidden();

    listingWeb()->actingAs($office)->post(route('admin.bookshop.listings.decide', $product->id), ['decision' => 'approve'])->assertSessionHasNoErrors();
    expect($product->fresh()->status->value)->toBe('active')->and($product->fresh()->reviewed_by)->toBe($office->id);
    listingWeb()->get(route('public.shop.product', $product->slug))->assertOk();
    expect(UserNotification::query()->where('user_id', $owner->id)->where('title', __('shop.notice_listing_approved_title'))->exists())->toBeTrue();
});

it('declines only with a note, which the shop reads on its product list', function () {
    [$vendor, $owner] = listingShop();
    $office = actingSystemAdmin(['bookshop.manage']);
    listingWeb()->actingAs($owner)->post(route('vendor.products.store'), listingInput());
    $product = Product::query()->sole();

    listingWeb()->actingAs($office)->post(route('admin.bookshop.listings.decide', $product->id), ['decision' => 'decline'])->assertSessionHasErrors('note');
    listingWeb()->actingAs($office)->post(route('admin.bookshop.listings.decide', $product->id), ['decision' => 'decline', 'note' => 'The cover photo is blurred'])
        ->assertSessionHasNoErrors();

    expect($product->fresh()->status->value)->toBe('draft')->and($product->fresh()->review_note)->toBe('The cover photo is blurred');
    listingWeb()->actingAs($owner)->get(route('vendor.index'))
        ->assertInertia(fn ($page) => $page->where('products.0.status', 'draft')->where('products.0.review_note', 'The cover photo is blurred'));

    // Deciding twice is refused.
    listingWeb()->actingAs($office)->post(route('admin.bookshop.listings.decide', $product->id), ['decision' => 'approve'])->assertSessionHasErrors('decision');
});

it('keeps a live product live through a price or stock change, and sends a new title back to the office', function () {
    [$vendor, $owner] = listingShop();
    $office = actingSystemAdmin(['bookshop.manage']);
    listingWeb()->actingAs($owner)->post(route('vendor.products.store'), listingInput());
    $product = Product::query()->sole();
    listingWeb()->actingAs($office)->post(route('admin.bookshop.listings.decide', $product->id), ['decision' => 'approve']);

    listingWeb()->actingAs($owner)->post(route('vendor.products.update', $product->id), listingInput(['price' => 99, 'stock' => 12]))->assertSessionHasNoErrors();
    expect($product->fresh()->status->value)->toBe('active')->and((string) $product->fresh()->price)->toBe('99.00');

    listingWeb()->actingAs($owner)->post(route('vendor.products.update', $product->id), listingInput(['title' => 'Seerah for young readers', 'price' => 99, 'stock' => 12]))->assertSessionHasNoErrors();
    $product->refresh();
    expect($product->status->value)->toBe('pending_review')
        ->and($product->review_changes)->toEqual(['title' => ['from' => 'Seerah for children', 'to' => 'Seerah for young readers']]); // JSON key order is the database's
    listingWeb()->get(route('public.shop.product', $product->slug))->assertNotFound();
    listingWeb()->actingAs($office)->get(route('admin.bookshop.index'))
        ->assertInertia(fn ($page) => $page->where('listings.0.changes.title.to', 'Seerah for young readers'));
});

it('sends a live product back when new photos or new variant names arrive', function () {
    [$vendor, $owner] = listingShop();
    $product = Product::query()->create(['vendor_id' => $vendor->id, 'slug' => 'prayer-mat', 'title' => 'Prayer Mat', 'price' => 180, 'currency' => 'MVR', 'tax_class' => 'standard', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop']);
    $input = listingInput(['title' => 'Prayer Mat', 'price' => 180, 'tax_class' => 'standard', 'track_stock' => 0]);

    listingWeb()->actingAs($owner)->post(route('vendor.products.update', $product->id), $input + ['photos' => [UploadedFile::fake()->image('mat.jpg', 600, 600)]])->assertSessionHasNoErrors();
    expect($product->fresh()->status->value)->toBe('pending_review')->and(array_keys($product->fresh()->review_changes))->toBe(['images']);

    $product->refresh()->forceFill(['status' => 'active', 'review_changes' => null])->save();
    listingWeb()->actingAs($owner)->post(route('vendor.products.update', $product->id), $input + ['variants_sent' => 1, 'variants' => [['name' => 'Green'], ['name' => 'Blue']]])->assertSessionHasNoErrors();
    expect($product->fresh()->status->value)->toBe('pending_review')->and(array_keys($product->fresh()->review_changes))->toBe(['variants']);
});

it('lets a trusted shop sell without the queue, and leaves products already on sale alone', function () {
    [$vendor, $owner] = listingShop(['trusted' => true]);
    $old = Product::query()->create(['vendor_id' => $vendor->id, 'slug' => 'old', 'title' => 'Old', 'price' => 10, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop']);

    listingWeb()->actingAs($owner)->post(route('vendor.products.store'), listingInput())->assertSessionHasNoErrors();
    expect(Product::query()->where('slug', '!=', 'old')->sole()->status->value)->toBe('active')
        ->and($old->fresh()->status->value)->toBe('active');

    // The office sets the flag on the shop.
    $office = actingSystemAdmin(['bookshop.manage']);
    listingWeb()->actingAs($office)->put(route('admin.bookshop.vendors.update', $vendor->id), ['name' => 'Fitrah', 'status' => 'active', 'trusted' => 0])->assertSessionHasNoErrors();
    expect($vendor->fresh()->trusted)->toBeFalse();
});

it('sends a bulk put-on-sale to the queue, and lists the queue in a CSV', function () {
    [$vendor, $owner] = listingShop();
    $office = actingSystemAdmin(['bookshop.manage']);
    $ids = collect(['one', 'two'])->map(fn ($slug) => Product::query()->create(['vendor_id' => $vendor->id, 'slug' => $slug, 'title' => ucfirst($slug), 'price' => 10, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'draft', 'visibility' => 'shop'])->id)->all();

    listingWeb()->actingAs($owner)->post(route('vendor.products.bulk'), ['ids' => $ids, 'status' => 'active'])->assertSessionHasNoErrors();
    expect(Product::query()->where('status', 'pending_review')->count())->toBe(2);
    expect(UserNotification::query()->where('user_id', $office->id)->where('message', __('shop.notice_listings_submitted_body', ['vendor' => 'Fitrah', 'count' => 2]))->exists())->toBeTrue();

    $csv = listingWeb()->actingAs($office)->get(route('admin.bookshop.listings.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('product,shop,category')->toContain('One,Fitrah')->toContain('pending_review');
    listingWeb()->actingAs($owner)->get(route('admin.bookshop.listings.export'))->assertForbidden();
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['status_pending_review', 'listings_title', 'listing_approval_hint', 'notice_listing_declined_body', 'trusted'] as $key) {
            expect(__("shop.{$key}", [], $locale))->not->toBe(__("shop.{$key}", [], 'en'))->not->toBe("shop.{$key}");
        }
    }
});
