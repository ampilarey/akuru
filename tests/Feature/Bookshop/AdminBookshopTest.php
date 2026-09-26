<?php

use App\Domains\Bookshop\Models\Brand;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN slice B1a, the office side: invite a vendor with its owner,
 * edit and suspend it, keep the shared categories and brands, export.
 */
function bookshopOffice(): User
{
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('bookshop.manage');

    return $admin;
}

function inviteVendor(User $office, array $overrides = [])
{
    return test()->withoutLocalizationMiddleware()->actingAs($office)->post(route('admin.bookshop.vendors.store'), $overrides + [
        'name' => 'Fitrah',
        'tagline' => 'iman.noor.ihsan',
        'owner_name' => 'Owner Person',
        'owner_email' => 'owner@example.test',
        'owner_phone' => '7000001',
    ]);
}

it('invites a vendor with a new owner who gets a one-time password shown once', function () {
    $office = bookshopOffice();

    $response = inviteVendor($office)->assertRedirect()->assertSessionHasNoErrors();

    $vendor = Vendor::query()->sole();
    expect($vendor->slug)->toBe('fitrah')
        ->and($vendor->code)->toBe('FIT')
        ->and($vendor->status->value)->toBe('active')
        ->and($vendor->contact_email)->toBe('owner@example.test');

    $owner = User::query()->where('email', 'owner@example.test')->sole();
    expect($owner->hasRole('vendor'))->toBeTrue()
        ->and($owner->force_password_change)->toBeTrue();

    $member = VendorMember::query()->sole();
    expect($member->user_id)->toBe($owner->id)
        ->and($member->role->value)->toBe('owner')
        ->and($member->agreement_accepted_at)->toBeNull();

    $invite = $response->getSession()->get('vendor_invite');
    expect($invite['existing_account'])->toBeFalse()
        ->and($invite['temporary_password'])->toBeString()->toHaveLength(12)
        ->and(Hash::check($invite['temporary_password'], $owner->password))->toBeTrue();
});

it('links an existing account without making a password, and keeps slugs and codes unique', function () {
    $office = bookshopOffice();
    $existing = User::factory()->create(['email' => 'reader@example.test']);
    $passwordBefore = $existing->password;

    $response = inviteVendor($office, ['owner_email' => 'reader@example.test']);
    expect($response->getSession()->get('vendor_invite'))->toMatchArray(['existing_account' => true, 'temporary_password' => null]);
    expect($existing->refresh()->password)->toBe($passwordBefore)
        ->and($existing->hasRole('vendor'))->toBeTrue();

    inviteVendor($office, ['owner_email' => 'second@example.test'])->assertSessionHasNoErrors();
    expect(Vendor::query()->orderBy('id')->pluck('slug')->all())->toBe(['fitrah', 'fitrah-2'])
        ->and(Vendor::query()->orderBy('id')->pluck('code')->all())->toBe(['FIT', 'FI2']);

    // A code the office asks for must be free.
    inviteVendor($office, ['name' => 'Other', 'code' => 'FIT', 'owner_email' => 'third@example.test'])->assertSessionHasErrors('code');
    expect(Vendor::query()->count())->toBe(2);
});

it('edits and suspends a vendor, and a suspended vendor\'s owner is shut out of the portal', function () {
    $office = bookshopOffice();
    inviteVendor($office);
    $vendor = Vendor::query()->sole();
    $owner = User::query()->where('email', 'owner@example.test')->sole();

    $this->withoutLocalizationMiddleware()->actingAs($owner)->get(route('vendor.index'))->assertOk();

    $this->withoutLocalizationMiddleware()->actingAs($office)->put(route('admin.bookshop.vendors.update', $vendor->id), [
        'name' => 'Fitrah Store', 'status' => 'suspended', 'commission_rate' => 12.5, 'tin' => '1234567GST001', 'gst_registered' => true,
        'office_notes' => 'Met on 25 Sep.',
    ])->assertSessionHasNoErrors();

    $vendor->refresh();
    expect($vendor->name)->toBe('Fitrah Store')
        ->and($vendor->slug)->toBe('fitrah')
        ->and($vendor->status->value)->toBe('suspended')
        ->and((string) $vendor->commission_rate)->toBe('12.50')
        ->and($vendor->gst_registered)->toBeTrue();

    $this->withoutLocalizationMiddleware()->actingAs($owner)->get(route('vendor.index'))->assertForbidden();
});

it('keeps the shared categories and brands and lists vendors with their owner and commission', function () {
    $office = bookshopOffice();
    inviteVendor($office);

    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.bookshop.categories.store'), ['name' => 'Stationery', 'name_dv' => 'ލިޔަންކިޔަން ތަކެތި', 'name_ar' => 'قرطاسية'])
        ->assertSessionHasNoErrors();
    $this->withoutLocalizationMiddleware()->actingAs($office)
        ->post(route('admin.bookshop.brands.store'), ['name' => 'Faber-Castell'])
        ->assertSessionHasNoErrors();

    expect(ProductCategory::query()->sole()->only(['name', 'slug', 'name_ar']))->toBe(['name' => 'Stationery', 'slug' => 'stationery', 'name_ar' => 'قرطاسية'])
        ->and(Brand::query()->sole()->slug)->toBe('faber-castell');

    $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.bookshop.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Bookshop/Admin')
            ->where('vendors.0.name', 'Fitrah')
            ->where('vendors.0.owners.0.email', 'owner@example.test')
            ->where('vendors.0.effective_commission_rate', '10.00')
            ->where('catalogue.categories.0.name', 'Stationery')
            ->where('catalogue.brands.0.name', 'Faber-Castell'));

    $csv = $this->withoutLocalizationMiddleware()->actingAs($office)->get(route('admin.bookshop.vendors.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('Fitrah')->toContain('owner@example.test')->toContain('10.00');
});

it('refuses the office screens to anyone without bookshop.manage', function () {
    $stranger = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($stranger)->get(route('admin.bookshop.index'))->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($stranger)->post(route('admin.bookshop.vendors.store'), [
        'name' => 'Sneaky', 'owner_name' => 'X', 'owner_email' => 'x@example.test',
    ])->assertForbidden();
    expect(Vendor::query()->count())->toBe(0);
});
