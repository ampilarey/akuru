<?php

use App\Domains\Bookshop\Enums\VendorMemberRole;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Bookshop\Models\VendorPage;
use App\Domains\Identity\Models\IdentityVerification;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * STATUS §5mq. The owner, 2026-10-01: "the changes made by shop owners to
 * their page are not showing". On production not one product was on sale,
 * so every product section was left off the live page as empty. The portal
 * now says, on its first screen, what stands between the shop's work and
 * its customers — the owner's ID card, the office's approval of each
 * listing, Publish on the design — and the designer says which sections
 * customers cannot see yet and why.
 */
beforeEach(function () {
    Storage::fake('public');
    config(['identity.verification.enforce' => true]);
});

function readyShop(): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active']);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => VendorMemberRole::Owner->value, 'agreement_accepted_at' => now()]);

    return [$vendor, $owner];
}

function readyWeb()
{
    return test()->withoutLocalizationMiddleware();
}

function readyProduct(Vendor $vendor, string $slug, string $status): Product
{
    return Product::query()->create(['vendor_id' => $vendor->id, 'slug' => $slug, 'title' => ucfirst($slug), 'price' => 50, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => $status, 'visibility' => 'shop']);
}

it('tells a new shop, step by step, why customers cannot see it yet — and goes green once they can', function () {
    [$vendor, $owner] = readyShop();
    readyProduct($vendor, 'seerah', 'pending_review');
    readyProduct($vendor, 'tracing', 'pending_review');
    readyProduct($vendor, 'puzzle', 'draft');

    readyWeb()->actingAs($owner)->get(route('vendor.index'))->assertOk()->assertInertia(fn ($p) => $p
        ->where('readiness.id.status', 'none')
        ->where('readiness.products.on_sale', 0)->where('readiness.products.waiting', 2)->where('readiness.products.drafts', 1)->where('readiness.products.total', 3)
        ->where('readiness.storefront.published', false)->where('readiness.storefront.dirty', false));

    // The card sent, waiting for the office; then refused, with the office's note; sent again and checked.
    Storage::fake('local');
    $office = actingSystemAdmin(['bookshop.manage']);
    $card = fn () => ['id_front' => UploadedFile::fake()->image('front.png', 600, 400), 'id_back' => UploadedFile::fake()->image('back.png', 600, 400)];
    readyWeb()->actingAs($owner)->post(route('vendor.identity'), $card())->assertSessionHasNoErrors();
    readyWeb()->actingAs($owner)->get(route('vendor.index'))->assertInertia(fn ($p) => $p->where('readiness.id.status', 'pending'));
    readyWeb()->actingAs($office)->post(route('identity.decide', IdentityVerification::query()->latest('id')->value('id')), ['decision' => 'reject', 'note' => 'The photo is blurred.'])->assertSessionHasNoErrors();
    readyWeb()->actingAs($owner)->get(route('vendor.index'))->assertInertia(fn ($p) => $p->where('readiness.id.status', 'rejected')->where('readiness.id.note', 'The photo is blurred.'));
    readyWeb()->actingAs($owner)->post(route('vendor.identity'), $card())->assertSessionHasNoErrors();
    readyWeb()->actingAs($office)->post(route('identity.decide', IdentityVerification::query()->latest('id')->value('id')), ['decision' => 'verify'])->assertSessionHasNoErrors();

    // Checked, two products approved, the design published: all three steps pass.
    Product::query()->where('status', 'pending_review')->update(['status' => 'active']);
    readyWeb()->actingAs($owner)->post(route('vendor.storefront.draft'), ['theme' => ['preset' => 'ocean']])->assertSessionHasNoErrors();
    readyWeb()->actingAs($owner)->get(route('vendor.index'))->assertInertia(fn ($p) => $p->where('readiness.storefront.dirty', true));
    readyWeb()->actingAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasNoErrors();
    readyWeb()->actingAs($owner)->get(route('vendor.index'))->assertInertia(fn ($p) => $p
        ->where('readiness.id.status', 'verified')->where('readiness.products.on_sale', 2)->where('readiness.products.waiting', 0)
        ->where('readiness.storefront.published', true)->where('readiness.storefront.dirty', false));

    // A page's unpublished draft counts as design work customers do not see.
    readyWeb()->actingAs($owner)->post(route('vendor.storefront.pages.store'), ['title' => 'About'])->assertSessionHasNoErrors();
    $page = VendorPage::query()->sole();
    readyWeb()->actingAs($owner)->post(route('vendor.storefront.pages.sections', $page->id), ['sections' => [['type' => 'faq', 'settings' => ['items' => [['question' => 'Q?', 'answer' => 'A.']]]]]])->assertSessionHasNoErrors();
    readyWeb()->actingAs($owner)->get(route('vendor.index'))->assertInertia(fn ($p) => $p->where('readiness.storefront.dirty', true));
});

it('tells the designer that product sections stay hidden while nothing is on sale, and marks them in the preview', function () {
    [$vendor, $owner] = readyShop();
    readyProduct($vendor, 'seerah', 'pending_review');
    readyWeb()->actingAs($owner)->post(route('vendor.storefront.draft'), ['theme' => ['preset' => 'ocean']])->assertSessionHasNoErrors();
    readyWeb()->actingAs($owner)->post(route('vendor.storefront.sections.save'), ['sections' => [
        ['type' => 'new_arrivals', 'settings' => ['heading' => 'Just in']],
        ['type' => 'faq', 'settings' => ['items' => [['question' => 'Do you deliver?', 'answer' => 'Yes.']]]],
    ]])->assertSessionHasNoErrors();

    readyWeb()->actingAs($owner)->get(route('vendor.storefront.sections'))->assertInertia(fn ($p) => $p->where('designer.on_sale', 0)->where('designer.waiting', 1));
    $preview = readyWeb()->actingAs($owner)->get(route('vendor.storefront.preview'))->assertOk()->getContent();
    // The empty New arrivals carries the note; the FAQ, which has content, does not.
    expect(substr_count($preview, 'data-testid="section-empty-note"'))->toBe(1)
        ->and(strpos($preview, 'data-section-type="new_arrivals"'))->toBeLessThan(strpos($preview, 'data-testid="section-empty-note"'))
        ->and(strpos($preview, 'data-testid="section-empty-note"'))->toBeLessThan(strpos($preview, 'data-section-type="faq"'));

    // Once something is on sale the note and the banner go.
    Product::query()->update(['status' => 'active']);
    readyWeb()->actingAs($owner)->get(route('vendor.storefront.sections'))->assertInertia(fn ($p) => $p->where('designer.on_sale', 1)->where('designer.waiting', 0));
    expect(readyWeb()->actingAs($owner)->get(route('vendor.storefront.preview'))->getContent())->not->toContain('data-testid="section-empty-note"');
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['ready_heading', 'ready_all_good', 'ready_id_none', 'ready_products_waiting', 'ready_design_dirty', 'sections_nothing_on_sale', 'section_empty_hidden', 'summary_notices', 'on_this_page'] as $key) {
            expect(__("shop.{$key}", [], $locale))->not->toBe(__("shop.{$key}", [], 'en'))->not->toBe("shop.{$key}");
        }
    }
});
