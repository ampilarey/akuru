<?php

use App\Domains\Bookshop\Enums\VendorMemberRole;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorApplication;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * LENDING_AND_USED_BOOKS_PLAN U1 (STATUS §5mr): old and used books. A
 * product carries a condition — new, or a used book's grade with a note —
 * shown on the card and the page, on the Used shelf and behind the Used
 * filter; a person may sell their own books as a personal seller, with
 * everything a shop has.
 */
beforeEach(function () {
    Storage::fake('public');
});

function usedShop(string $kind = 'shop'): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active', 'trusted' => true, 'kind' => $kind]);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => VendorMemberRole::Owner->value, 'agreement_accepted_at' => now()]);

    return [$vendor, $owner];
}

function usedWeb()
{
    return test()->withoutLocalizationMiddleware();
}

function usedInput(array $overrides = []): array
{
    return $overrides + ['title' => 'Grade 5 Dhivehi reader', 'price' => 40, 'tax_class' => 'zero_rated', 'track_stock' => 1, 'stock' => 1, 'status' => 'active', 'visibility' => 'shop'];
}

it('sells a used book with its grade and note, on the card, the page, the Used shelf and behind the Used filter', function () {
    [$vendor, $owner] = usedShop();

    usedWeb()->actingAs($owner)->post(route('vendor.products.store'), usedInput(['condition' => 'mint']))->assertSessionHasErrors('condition');
    usedWeb()->actingAs($owner)->post(route('vendor.products.store'), usedInput(['condition' => 'good', 'condition_note' => 'Name written inside the cover; page 12 has a pencil mark.']))->assertSessionHasNoErrors();
    usedWeb()->actingAs($owner)->post(route('vendor.products.store'), usedInput(['title' => 'Brand new atlas', 'price' => 120]))->assertSessionHasNoErrors();
    $used = Product::query()->where('title', 'Grade 5 Dhivehi reader')->sole();
    $new = Product::query()->where('title', 'Brand new atlas')->sole();
    expect($used->condition->value)->toBe('good')->and($used->condition_note)->toStartWith('Name written')->and($new->condition->value)->toBe('new')->and($new->condition_note)->toBeNull();

    // The vendor's own list names the grade.
    $portal = usedWeb()->actingAs($owner)->get(route('vendor.index'))->assertInertia(fn ($p) => $p->has('products', 2)->where('options.conditions', ['new', 'like_new', 'good', 'fair', 'worn']));
    expect(collect($portal->viewData('page')['props']['products'])->pluck('condition', 'title')->all())->toBe(['Brand new atlas' => 'new', 'Grade 5 Dhivehi reader' => 'good']);

    // The card badge and the page's note; the new atlas has neither.
    $page = usedWeb()->get(route('public.shop.product', $used->slug))->assertOk();
    $page->assertSee('data-testid="product-condition"', false)->assertSee('Used · Good')->assertSee('page 12 has a pencil mark')->assertSee('schema.org/UsedCondition', false);
    usedWeb()->get(route('public.shop.product', $new->slug))->assertOk()->assertDontSee('data-testid="product-condition"', false)->assertSee('schema.org/NewCondition', false);

    // The Used shelf and the menu link on the front; the Used page and filter list only the used one.
    $home = usedWeb()->get(route('public.shop.index'))->assertOk();
    $home->assertSee('data-testid="shop-used"', false)->assertSee('data-testid="shop-link-used"', false)->assertSee('data-badge="condition"', false);
    $usedPage = usedWeb()->get(route('public.shop.used'))->assertOk();
    $usedPage->assertSee('Old and used books')->assertSee('data-product="'.$used->slug.'"', false)->assertDontSee('data-product="'.$new->slug.'"', false);
    usedWeb()->get(route('public.shop.index', ['used' => 1]))->assertOk()->assertSee('data-product="'.$used->slug.'"', false)->assertDontSee('data-product="'.$new->slug.'"', false);
    usedWeb()->get(route('public.shop.index'))->assertSee('data-product="'.$new->slug.'"', false);

    // The CSV carries the grade, and an import can set it.
    $csv = usedWeb()->actingAs($owner)->get(route('vendor.products.export'))->streamedContent();
    expect(str_getcsv(strtok($csv, "\n")))->toContain('condition');
    $rows = array_map('str_getcsv', array_filter(explode("\n", $csv)));
    $header = array_flip($rows[0]);
    $byTitle = collect(array_slice($rows, 1))->keyBy(fn ($r) => $r[$header['title']]);
    expect($byTitle['Grade 5 Dhivehi reader'][$header['condition']])->toBe('good')->and($byTitle['Brand new atlas'][$header['condition']])->toBe('new');

    // The catalogue API passes the grade through its cards.
    usedWeb()->getJson(route('api.bookstore.products', ['used' => 1]))->assertOk()->assertJsonFragment(['condition' => 'good']);
});

it('lets a person apply as a personal seller, and says so on the application, the shop head and the product page', function () {
    Storage::fake('local');
    config(['identity.verification.enforce' => true]);
    Role::findOrCreate('vendor', 'web');
    $office = actingSystemAdmin(['bookshop.manage']);
    $person = User::factory()->create(['name' => 'Aminath Reader']);
    $card = ['id_front' => UploadedFile::fake()->image('front.png', 600, 400), 'id_back' => UploadedFile::fake()->image('back.png', 600, 400)];

    usedWeb()->actingAs($person)->post(route('vendor.apply.store'), [
        'kind' => 'personal', 'shop_name' => 'Aminath\'s old books', 'contact_email' => 'aminath@example.test', 'contact_phone' => '7700999', 'island' => 'Malé',
        'what_they_sell' => 'About twenty grade 4 to 7 textbooks my children have finished with.', 'agreement' => 1,
    ] + $card)->assertSessionHasNoErrors();
    $application = VendorApplication::query()->sole();
    expect($application->kind)->toBe('personal');
    usedWeb()->actingAs($person)->get(route('vendor.apply'))->assertInertia(fn ($p) => $p->where('application.kind', 'personal'));
    usedWeb()->actingAs($office)->get(route('admin.bookshop.index'))->assertInertia(fn ($p) => $p->where('applications.0.kind', 'personal'));

    usedWeb()->actingAs($office)->post(route('admin.bookshop.applications.decide', $application->id), ['decision' => 'approve'])->assertSessionHas('success');
    $vendor = Vendor::query()->where('name', 'Aminath\'s old books')->sole();
    expect($vendor->kind)->toBe('personal')->and($vendor->isPersonal())->toBeTrue();

    // A shop's own application stays a shop.
    $other = User::factory()->create();
    usedWeb()->actingAs($other)->post(route('vendor.apply.store'), [
        'kind' => 'shop', 'shop_name' => 'Noor Stationery', 'contact_email' => 'noor@example.test', 'contact_phone' => '7771234', 'island' => 'Hulhumalé',
        'what_they_sell' => 'Exercise books, pencils and Dhivehi alphabet charts for primary pupils.', 'agreement' => 1,
    ] + ['id_front' => UploadedFile::fake()->image('f.png', 600, 400), 'id_back' => UploadedFile::fake()->image('b.png', 600, 400)])->assertSessionHasNoErrors();
    expect(VendorApplication::query()->where('shop_name', 'Noor Stationery')->value('kind'))->toBe('shop');

    // Her listing, once approved by the office, says who is selling.
    $vendor->update(['trusted' => true]);
    usedWeb()->actingAs($person)->post(route('vendor.products.store'), usedInput(['condition' => 'fair', 'condition_note' => 'Cover creased.']))->assertSessionHasNoErrors();
    $product = Product::query()->where('vendor_id', $vendor->id)->sole();
    usedWeb()->get(route('public.shop.product', $product->slug))->assertOk()->assertSee('data-testid="personal-seller"', false)->assertSee('Personal seller');
    usedWeb()->get(route('public.shop.vendor', $vendor->slug))->assertOk()->assertSee('data-testid="personal-seller"', false);
    usedWeb()->get(route('public.shop.vendor', 'fitrah'))->assertNotFound();
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['condition_label', 'condition_good', 'used_heading', 'used_only', 'used_badge', 'quick_add_used', 'apply_kind_personal', 'personal_seller'] as $key) {
            expect(__("shop.{$key}", [], $locale))->not->toBe(__("shop.{$key}", [], 'en'))->not->toBe("shop.{$key}");
        }
    }
});
