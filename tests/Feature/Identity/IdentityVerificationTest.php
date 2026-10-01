<?php

use App\Domains\Bookshop\Enums\VendorMemberRole;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorApplication;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Identity\Actions\IdentityVerificationAction;
use App\Domains\Identity\Models\IdentityVerification;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\SubmitLibraryItemForReviewAction;
use App\Domains\Library\Models\WriterApplication;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * COMMERCE_PARITY_PLAN P2: shops and writers upload the front and back of
 * their ID card; the office's approval is the verification; until then a
 * shop cannot put a product on sale or ask for a payout, and a writer cannot
 * submit a work or ask for a payout. Only the office sees the images.
 */
beforeEach(function () {
    config(['identity.verification.enforce' => true]);
    Storage::fake('local');
    Storage::fake('public');
});

function idCard(): array
{
    return ['id_front' => UploadedFile::fake()->image('front.png', 600, 400), 'id_back' => UploadedFile::fake()->image('back.png', 600, 400)];
}

function idWeb()
{
    return test()->withoutLocalizationMiddleware();
}

function idShop(User $owner): Vendor
{
    $vendor = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active']);
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => VendorMemberRole::Owner->value, 'agreement_accepted_at' => now()]);

    return $vendor;
}

/** Someone who holds only this permission — not a super admin, who sees everything. */
function idOfficer(string $permission): User
{
    Permission::findOrCreate($permission, 'web');
    $user = User::factory()->create();
    $user->givePermissionTo($permission);

    return $user;
}

function idProductInput(array $overrides = []): array
{
    return $overrides + ['title' => 'Seerah for children', 'price' => 120, 'tax_class' => 'zero_rated', 'track_stock' => 0, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop'];
}

it('takes both sides with a shop application, shows them to the Bookstore office only, and approval verifies them', function () {
    $person = User::factory()->create();
    $office = actingSystemAdmin(['bookshop.manage']);

    idWeb()->actingAs($person)->post(route('vendor.apply.store'), [
        'shop_name' => 'Noor Stationery', 'contact_email' => 'noor@example.test', 'contact_phone' => '7771234', 'island' => 'Hulhumalé',
        'what_they_sell' => 'Exercise books, pencils and alphabet charts for primary pupils.', 'agreement' => 1,
    ])->assertSessionHasErrors(['id_front', 'id_back']);

    idWeb()->actingAs($person)->post(route('vendor.apply.store'), idCard() + [
        'shop_name' => 'Noor Stationery', 'contact_email' => 'noor@example.test', 'contact_phone' => '7771234', 'island' => 'Hulhumalé',
        'what_they_sell' => 'Exercise books, pencils and alphabet charts for primary pupils.', 'agreement' => 1,
    ])->assertSessionHasNoErrors();

    $card = IdentityVerification::query()->where('user_id', $person->id)->firstOrFail();
    expect($card->purpose)->toBe('vendor')->and($card->status)->toBe('pending');

    // The office sees both sides; the person, and the Library office, do not.
    idWeb()->actingAs($office)->get(route('identity.document', [$card->id, 'front']))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    idWeb()->actingAs($office)->get(route('identity.document', [$card->id, 'back']))->assertOk();
    idWeb()->actingAs($person)->get(route('identity.document', [$card->id, 'front']))->assertForbidden();
    idWeb()->actingAs(idOfficer('library.manage'))->get(route('identity.document', [$card->id, 'front']))->assertForbidden();

    $application = VendorApplication::query()->firstOrFail();
    idWeb()->actingAs($office)->post(route('admin.bookshop.applications.decide', $application->id), ['decision' => 'approve'])->assertSessionHasNoErrors();
    expect($card->fresh()->status)->toBe('verified');
});

it('keeps an unverified shop from putting a product on sale or asking for a payout, and lets it once verified', function () {
    $owner = User::factory()->create();
    $vendor = idShop($owner);
    $office = actingSystemAdmin(['bookshop.manage']);

    idWeb()->actingAs($owner)->post(route('vendor.products.store'), idProductInput())->assertSessionHasErrors(['status' => __('account.id_needed_vendor')]);
    idWeb()->actingAs($owner)->post(route('vendor.products.store'), idProductInput(['status' => 'draft']))->assertSessionHasNoErrors();
    expect(Product::query()->where('vendor_id', $vendor->id)->where('status', 'active')->exists())->toBeFalse();

    // The portal asks for the card; the owner sends it; the office verifies.
    idWeb()->actingAs($owner)->get(route('vendor.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('identity.status', 'none'));
    idWeb()->actingAs($owner)->post(route('vendor.identity'), idCard())->assertSessionHasNoErrors();
    $card = IdentityVerification::query()->where('user_id', $owner->id)->firstOrFail();
    idWeb()->actingAs($office)->post(route('identity.decide', $card->id), ['decision' => 'verify'])->assertSessionHasNoErrors();

    // Verified, the request to sell goes through — to the office's approval queue (P4).
    idWeb()->actingAs($owner)->post(route('vendor.products.store'), idProductInput(['title' => 'Dua cards']))->assertSessionHasNoErrors();
    expect(Product::query()->where('vendor_id', $vendor->id)->where('status', 'pending_review')->count())->toBe(1);
    idWeb()->actingAs($owner)->get(route('vendor.index'))->assertInertia(fn ($page) => $page->where('identity', null));
});

it('leaves products already on sale on sale', function () {
    $owner = User::factory()->create();
    $vendor = idShop($owner);
    $product = Product::query()->create(['vendor_id' => $vendor->id, 'slug' => 'old', 'title' => 'Old', 'price' => 10, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop']);

    idWeb()->actingAs($owner)->put(route('vendor.products.update', $product->id), idProductInput(['title' => 'Old, renamed']))->assertSessionHasNoErrors();
    expect($product->fresh()->status->value)->toBe('active');
});

it('asks a rejected card again, with the office\'s note, and refuses a rejection without one', function () {
    $owner = User::factory()->create();
    idShop($owner);
    $office = actingSystemAdmin(['bookshop.manage']);
    idWeb()->actingAs($owner)->post(route('vendor.identity'), idCard());
    $card = IdentityVerification::query()->firstOrFail();

    idWeb()->actingAs($office)->post(route('identity.decide', $card->id), ['decision' => 'reject'])->assertSessionHasErrors('note');
    idWeb()->actingAs($office)->post(route('identity.decide', $card->id), ['decision' => 'reject', 'note' => 'The back is blurred'])->assertSessionHasNoErrors();

    idWeb()->actingAs($owner)->get(route('vendor.index'))
        ->assertInertia(fn ($page) => $page->where('identity.status', 'rejected')->where('identity.note', 'The back is blurred'));
});

it('makes a writer send both sides, and keeps an unverified writer from submitting or asking for a payout', function () {
    $writer = User::factory()->create();
    $office = actingSystemAdmin(['library.manage']);

    idWeb()->actingAs($writer)->post(route('write.apply'), ['display_name' => 'Ustadh Ali', 'agreement_accepted' => '1'])
        ->assertSessionHasErrors(['id_front', 'id_back']);
    idWeb()->actingAs($writer)->post(route('write.apply'), idCard() + ['display_name' => 'Ustadh Ali', 'agreement_accepted' => '1'])
        ->assertSessionHasNoErrors();
    $application = WriterApplication::query()->firstOrFail();

    // A Bookstore office cannot open a writer's card.
    $card = IdentityVerification::query()->where('user_id', $writer->id)->firstOrFail();
    idWeb()->actingAs(idOfficer('bookshop.manage'))->get(route('identity.document', [$card->id, 'front']))->assertForbidden();

    idWeb()->actingAs($office)->post(route('admin.library.applications.decide', $application->id), ['approve' => 1])->assertSessionHasNoErrors();
    expect($card->fresh()->status)->toBe('verified')
        ->and(app(IdentityVerificationAction::class)->isVerified($writer->id, 'writer'))->toBeTrue();

    // A writer approved before the rule, with no card: the portal asks, and submitting waits.
    $old = User::factory()->create();
    WriterProfile::query()->create(['user_id' => $old->id, 'display_name' => 'Old Writer', 'slug' => 'old-writer', 'status' => 'active', 'approved_at' => now()]);
    idWeb()->actingAs($old)->get(route('write.index'))->assertInertia(fn ($page) => $page->where('identity.status', 'none'));
    // The gate stands before the item is even looked up.
    expect(fn () => app(SubmitLibraryItemForReviewAction::class)->execute($old->id, 999999))
        ->toThrow(ValidationException::class, __('account.id_needed_writer'));
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        expect(__('account.id_verify_title', [], $locale))->not->toBe(__('account.id_verify_title', [], 'en'))
            ->and(__('account.id_needed_vendor', [], $locale))->not->toBe('account.id_needed_vendor');
    }
});
