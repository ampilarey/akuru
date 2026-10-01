<?php

use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderComplaint;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * COMMERCE_PARITY_PLAN P7a: *Report a problem* on an order. The customer
 * reports (with a photo if there is one); the office and the shop are told;
 * the office answers and the customer hears it; the shop reads the problem
 * on its order; the photo opens for those three and nobody else.
 */
beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
});

function complainWeb()
{
    return test()->withoutLocalizationMiddleware();
}

function complainOffice(): User
{
    Role::findOrCreate('super_admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('super_admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

/** @return array{0: Order, 1: User, 2: User} the order, its customer and the shop's owner */
function complainOrder(): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active']);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    $method = VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'courier_male', 'name' => 'Our courier', 'fee' => 25, 'handling_days' => 1, 'is_active' => true]);
    $book = Product::query()->create(['vendor_id' => $vendor->id, 'slug' => 'fitrah-book', 'title' => 'Tracing Book', 'price' => 100, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop']);

    $customer = User::factory()->create(['phone' => '7712345']);
    app(CreditWalletAction::class)->execute($customer->id, 5000, 'admin', null, 'Top-up');
    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => 1]);
    complainWeb()->actingAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath', 'phone' => '7712345', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => [$vendor->slug => 'm'.$method->id], 'payment_method' => 'wallet',
    ])->assertSessionHasNoErrors();

    return [Order::query()->where('user_id', $customer->id)->latest('id')->firstOrFail(), $customer, $owner];
}

it('lets the customer report a problem with a photo, and tells the office and the shop', function () {
    [$order, $customer, $owner] = complainOrder();
    $office = complainOffice();

    complainWeb()->actingAs($customer)->get(route('public.shop.orders.show', $order->number))->assertOk()->assertSee('data-testid="report-problem"', false);
    complainWeb()->actingAs($customer)->post(route('public.shop.orders.complain', $order->number), [
        'kind' => 'damaged', 'body' => 'The cover is torn.', 'photo' => UploadedFile::fake()->image('torn.jpg', 800, 600),
    ])->assertSessionHasNoErrors()->assertSessionHas('success', __('shop.complaint_sent_flash'));

    $complaint = OrderComplaint::query()->sole();
    expect($complaint->status)->toBe('open')->and($complaint->vendor_id)->toBe($order->vendor_id)->and($complaint->photo_media_file_id)->not->toBeNull();
    $title = __('shop.notice_complaint_title', ['number' => $order->number]);
    expect(UserNotification::query()->where('user_id', $owner->id)->where('title', $title)->exists())->toBeTrue()
        ->and(UserNotification::query()->where('user_id', $office->id)->where('title', $title)->exists())->toBeTrue();

    // The customer's order page shows it; so does the shop's order list.
    complainWeb()->actingAs($customer)->get(route('public.shop.orders.show', $order->number))->assertSee('The cover is torn.');
    complainWeb()->actingAs($owner)->get(route('vendor.orders.index'))
        ->assertInertia(fn ($page) => $page->where('orders.0.complaints.0.kind', 'damaged')->where('orders.0.complaints.0.status', 'open'));
});

it('lets the office answer, and the customer hears it', function () {
    [$order, $customer] = complainOrder();
    $office = complainOffice();
    complainWeb()->actingAs($customer)->post(route('public.shop.orders.complain', $order->number), ['kind' => 'missing', 'body' => 'One book short.']);
    $complaint = OrderComplaint::query()->sole();

    complainWeb()->actingAs($office)->get(route('admin.bookshop.complaints'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Bookshop/Complaints')->where('complaints.0.number', $order->number)->where('complaints.0.recipient', 'Aishath'));
    complainWeb()->actingAs($office)->post(route('admin.bookshop.complaints.reply', $complaint->id), ['status' => 'open', 'reply' => 'x'])->assertSessionHasErrors('status');
    complainWeb()->actingAs($office)->post(route('admin.bookshop.complaints.reply', $complaint->id), ['status' => 'resolved', 'reply' => 'We are sending the missing book today.'])->assertSessionHasNoErrors();

    $complaint->refresh();
    expect($complaint->status)->toBe('resolved')->and($complaint->replied_by)->toBe($office->id)->and($complaint->resolved_at)->not->toBeNull()
        ->and(UserNotification::query()->where('user_id', $customer->id)->where('title', __('shop.notice_complaint_reply_title', ['number' => $order->number]))->exists())->toBeTrue();
    complainWeb()->actingAs($customer)->get(route('public.shop.orders.show', $order->number))->assertSee('We are sending the missing book today.');

    $csv = complainWeb()->actingAs($office)->get(route('admin.bookshop.complaints.export'))->streamedContent();
    expect($csv)->toContain($order->number)->toContain('One book short.');
    complainWeb()->actingAs($customer)->get(route('admin.bookshop.complaints'))->assertForbidden();
    complainWeb()->actingAs($customer)->post(route('admin.bookshop.complaints.reply', $complaint->id), ['status' => 'resolved', 'reply' => 'x'])->assertForbidden();
});

it('opens the photo for the customer, the shop and the office, and nobody else', function () {
    [$order, $customer, $owner] = complainOrder();
    $office = complainOffice();
    complainWeb()->actingAs($customer)->post(route('public.shop.orders.complain', $order->number), ['kind' => 'wrong_item', 'body' => 'Not what I ordered.', 'photo' => UploadedFile::fake()->image('wrong.jpg')]);
    $complaint = OrderComplaint::query()->sole();

    foreach ([$customer, $owner, $office] as $who) {
        complainWeb()->actingAs($who)->get(route('complaints.photo', $complaint->id))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }
    complainWeb()->actingAs(User::factory()->create())->get(route('complaints.photo', $complaint->id))->assertForbidden();
});

it('refuses a report on someone else\'s order, an unpaid order, or an unknown kind', function () {
    [$order, $customer] = complainOrder();

    complainWeb()->actingAs(User::factory()->create())->post(route('public.shop.orders.complain', $order->number), ['kind' => 'late', 'body' => 'Where is it?'])->assertNotFound();
    complainWeb()->actingAs($customer)->post(route('public.shop.orders.complain', $order->number), ['kind' => 'broken', 'body' => 'x'])->assertSessionHasErrors('kind');
    $order->forceFill(['status' => 'pending_payment'])->save();
    complainWeb()->actingAs($customer)->post(route('public.shop.orders.complain', $order->number), ['kind' => 'late', 'body' => 'Where is it?'])->assertSessionHasErrors('body');
    expect(OrderComplaint::query()->count())->toBe(0);
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['complaint_report', 'complaint_kind_damaged', 'complaint_status_resolved', 'complaints_title', 'notice_complaint_title', 'notice_event_complaint'] as $key) {
            expect(__("shop.{$key}", [], $locale))->not->toBe(__("shop.{$key}", [], 'en'))->not->toBe("shop.{$key}");
        }
    }
});
