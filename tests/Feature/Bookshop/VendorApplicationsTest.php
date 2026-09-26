<?php

use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorApplication;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN slice B9a, public vendor onboarding (§3 "apply → approve,
 * like writers"): a signed-in person applies; the office approves (the
 * shop is created with them as owner, agreement dated) or declines with a
 * note; the office can close the form.
 */
function applyAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

function applyInput(array $overrides = []): array
{
    return $overrides + [
        'shop_name' => 'Noor Stationery', 'legal_name' => 'Noor Trading Pvt Ltd', 'tin' => '1009999GST501',
        'contact_email' => 'noor@example.test', 'contact_phone' => '7771234', 'island' => 'Hulhumalé',
        'what_they_sell' => 'Exercise books, pencils and Dhivehi alphabet charts for primary pupils.',
        'link' => 'https://instagram.com/noor.stationery', 'agreement' => 1,
    ];
}

function applyOffice(): User
{
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('vendor', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

it('lets a signed-in person apply once, with the agreement, and tells the office', function () {
    $office = applyOffice();
    $person = User::factory()->create();

    applyAs($person)->get(route('vendor.index'))->assertRedirect(route('vendor.apply'));
    applyAs($person)->get(route('vendor.apply'))->assertOk()->assertInertia(fn ($page) => $page->component('Bookshop/VendorApply')->where('open', true)->where('application', null));
    applyAs($person)->post(route('vendor.apply.store'), applyInput(['agreement' => 0]))->assertSessionHasErrors('agreement');
    applyAs($person)->post(route('vendor.apply.store'), applyInput(['what_they_sell' => 'Books']))->assertSessionHasErrors('what_they_sell');
    applyAs($person)->post(route('vendor.apply.store'), applyInput())->assertRedirect(route('vendor.apply'))->assertSessionHas('success');

    $application = VendorApplication::query()->sole();
    expect($application->status)->toBe('pending')->and($application->user_id)->toBe($person->id)->and($application->agreement_accepted_at)->not->toBeNull();
    expect(UserNotification::query()->where('user_id', $office->id)->where('title', __('shop.notice_application_title'))->exists())->toBeTrue();

    // One waiting application at a time.
    applyAs($person)->post(route('vendor.apply.store'), applyInput(['shop_name' => 'Second']))->assertSessionHasErrors('shop_name');
    applyAs($person)->get(route('vendor.apply'))->assertInertia(fn ($page) => $page->where('application.status', 'pending'));
    // Guests are sent to sign in.
    app('auth')->forgetGuards();
    $this->withoutLocalizationMiddleware()->get(route('vendor.apply'))->assertRedirect();
});

it('approves: the shop is created with the applicant as owner, agreement dated, portal open straight away', function () {
    $office = applyOffice();
    $person = User::factory()->create(['name' => 'Aminath Noor']);
    applyAs($person)->post(route('vendor.apply.store'), applyInput());
    $application = VendorApplication::query()->sole();

    applyAs($person)->post(route('admin.bookshop.applications.decide', $application->id), ['decision' => 'approve'])->assertForbidden();
    applyAs($office)->post(route('admin.bookshop.applications.decide', $application->id), ['decision' => 'approve', 'commission_rate' => 8, 'code' => 'nsr'])->assertSessionHas('success');

    $vendor = Vendor::query()->where('name', 'Noor Stationery')->sole();
    expect($vendor->slug)->toBe('noor-stationery')->and($vendor->code)->toBe('NSR')->and((string) $vendor->commission_rate)->toBe('8.00')
        ->and($vendor->legal_name)->toBe('Noor Trading Pvt Ltd')->and($vendor->contact_phone)->toBe('7771234');
    $member = VendorMember::query()->where('vendor_id', $vendor->id)->sole();
    expect($member->user_id)->toBe($person->id)->and($member->role->value)->toBe('owner')->and($member->agreement_accepted_at)->not->toBeNull();
    expect($person->refresh()->hasRole('vendor'))->toBeTrue();
    expect($application->refresh()->status)->toBe('approved')->and($application->vendor_id)->toBe($vendor->id);
    expect(UserNotification::query()->where('user_id', $person->id)->where('title', __('shop.notice_application_approved_title'))->exists())->toBeTrue();

    applyAs($person)->get(route('vendor.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Bookshop/Vendor')->where('vendor.agreement_accepted', true)->where('vendor.role', 'owner'));
    // Decided once.
    applyAs($office)->post(route('admin.bookshop.applications.decide', $application->id), ['decision' => 'decline', 'note' => 'late'])->assertSessionHasErrors('application');
    expect(Vendor::query()->count())->toBe(1);
});

it('declines with a note the applicant reads, and lets them apply again', function () {
    $office = applyOffice();
    $person = User::factory()->create();
    applyAs($person)->post(route('vendor.apply.store'), applyInput());
    $application = VendorApplication::query()->sole();

    applyAs($office)->post(route('admin.bookshop.applications.decide', $application->id), ['decision' => 'decline'])->assertSessionHasErrors('note');
    applyAs($office)->post(route('admin.bookshop.applications.decide', $application->id), ['decision' => 'decline', 'note' => 'We only take book and stationery shops for now.'])->assertSessionHas('success');

    expect($application->refresh()->status)->toBe('declined')->and(Vendor::query()->count())->toBe(0);
    applyAs($person)->get(route('vendor.apply'))->assertInertia(fn ($page) => $page->where('application.status', 'declined')->where('application.decision_note', 'We only take book and stationery shops for now.'));
    applyAs($person)->post(route('vendor.apply.store'), applyInput(['shop_name' => 'Noor Books']))->assertSessionHas('success');
    expect(VendorApplication::query()->count())->toBe(2);

    $csv = applyAs($office)->get(route('admin.bookshop.applications.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('Noor Books')->toContain('We only take book and stationery shops for now.');
    applyAs($office)->get(route('admin.bookshop.index'))->assertOk()->assertInertia(fn ($page) => $page->where('applications.0.shop_name', 'Noor Books')->where('applications_open', true));
});

it('lets the office close and reopen the form, and the shop home follows', function () {
    $office = applyOffice();
    $person = User::factory()->create();

    $this->withoutLocalizationMiddleware()->get(route('public.shop.index'))->assertOk()->assertSee(__('shop.sell_here_heading'));
    applyAs($office)->post(route('admin.bookshop.applications.open'), ['open' => 0])->assertSessionHas('success');
    app('auth')->forgetGuards();
    $this->withoutLocalizationMiddleware()->get(route('public.shop.index'))->assertOk()->assertDontSee(__('shop.sell_here_heading'));
    applyAs($person)->get(route('vendor.apply'))->assertInertia(fn ($page) => $page->where('open', false));
    applyAs($person)->post(route('vendor.apply.store'), applyInput())->assertSessionHasErrors('shop_name');
    expect(VendorApplication::query()->count())->toBe(0);

    applyAs($office)->post(route('admin.bookshop.applications.open'), ['open' => 1]);
    applyAs($person)->post(route('vendor.apply.store'), applyInput())->assertSessionHas('success');
});
