<?php

use App\Domains\Bookshop\Mail\BookshopNoticeMail;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Actions\StartLibraryCheckoutAction;
use App\Domains\Library\Mail\LibraryNoticeMail;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * COMMERCE_PARITY_PLAN P5 — the owner: "SMS and email to vendor, customer
 * and admin on every purchase". A paid bookstore order reaches the customer,
 * the shop and the office in the app, by email and by SMS; so does a Digital
 * Library sale (reader, writer, office). The office's switches silence a
 * channel; an office with no number set gets no SMS, and nothing fails.
 */
function purchaseSms(): object
{
    $sms = new class implements SmsSenderInterface
    {
        public array $sent = [];

        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            $this->sent[] = [$phoneNumber, $message];

            return ['success' => true, 'driver' => 'log'];
        }

        public function sendOtp(string $phoneNumber, string $otp): array
        {
            return ['success' => true, 'driver' => 'log'];
        }
    };
    app()->instance(SmsSenderInterface::class, $sms);

    return $sms;
}

function purchaseOffice(string $permission): User
{
    Role::findOrCreate('super_admin', 'web');
    Permission::findOrCreate($permission, 'web');
    $office = User::factory()->create(['email' => 'office-'.$permission.'@example.test']);
    $office->assignRole('super_admin');
    $office->givePermissionTo($permission);

    return $office;
}

/** @return array{0: Vendor, 1: User, 2: Product} */
function purchaseShop(): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active', 'contact_phone' => '7000001']);
    $owner = User::factory()->create(['email' => 'fitrah-owner@example.test']);
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'courier_male', 'name' => 'Courier', 'fee' => 30, 'handling_days' => 1, 'is_active' => true]);
    $book = Product::query()->create(['vendor_id' => $vendor->id, 'slug' => 'tracing-book', 'title' => 'Tracing Book', 'price' => 85, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => 20, 'status' => 'active', 'visibility' => 'shop']);

    return [$vendor, $owner, $book];
}

function purchaseBuy(Vendor $vendor, Product $book): array
{
    $customer = User::factory()->create(['phone' => '7712345', 'email' => 'buyer'.uniqid().'@example.test']);
    app(CreditWalletAction::class)->execute($customer->id, 5000, 'admin', null, 'Top-up');
    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => 1]);
    test()->withoutLocalizationMiddleware()->actingAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath', 'phone' => '7712345', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => [$vendor->slug => 'm'.VendorDeliveryMethod::query()->where('vendor_id', $vendor->id)->value('id')], 'payment_method' => 'wallet',
    ])->assertSessionHasNoErrors();

    return [$customer, Order::query()->where('user_id', $customer->id)->latest('id')->firstOrFail()];
}

it('tells the customer, the shop and the office of a paid order — in the app, by email and by SMS', function () {
    Mail::fake();
    $sms = purchaseSms();
    [$vendor, $owner, $book] = purchaseShop();
    $office = purchaseOffice('bookshop.manage');
    test()->withoutLocalizationMiddleware()->actingAs($office)->post(route('admin.bookshop.notices.save'), [
        'customer_email' => 1, 'customer_sms' => 1, 'vendor_email' => 1, 'vendor_sms' => 1, 'office_email' => 1, 'office_sms' => 1,
        'office_contact_email' => 'shop-office@example.test', 'office_contact_phone' => '7009999',
    ])->assertSessionHasNoErrors();
    auth()->logout();

    [$customer, $order] = purchaseBuy($vendor, $book);

    // In the app: all three.
    expect(UserNotification::query()->where('user_id', $customer->id)->where('title', __('shop.notice_paid_title'))->exists())->toBeTrue()
        ->and(UserNotification::query()->where('user_id', $owner->id)->where('title', __('shop.notice_vendor_order_title'))->exists())->toBeTrue()
        ->and(UserNotification::query()->where('user_id', $office->id)->where('title', __('shop.notice_office_paid_title'))->exists())->toBeTrue();
    // By email: all three.
    Mail::assertQueued(BookshopNoticeMail::class, fn ($m) => $m->hasTo($customer->email) && $m->heading === __('shop.notice_paid_title'));
    Mail::assertQueued(BookshopNoticeMail::class, fn ($m) => $m->hasTo('fitrah-owner@example.test') && $m->heading === __('shop.notice_vendor_order_title'));
    Mail::assertQueued(BookshopNoticeMail::class, fn ($m) => $m->hasTo('shop-office@example.test') && $m->heading === __('shop.notice_office_paid_title'));
    // By SMS: all three; the customer's carries the tracking link.
    $to = collect($sms->sent)->pluck(0)->all();
    expect($to)->toBe(['7712345', '7009999', '7000001'])
        ->and($sms->sent[0][1])->toContain(route('public.shop.track', ['number' => $order->number]));
});

it('silences the office by its switch, and sends no SMS to an office with no number', function () {
    Mail::fake();
    $sms = purchaseSms();
    [$vendor, , $book] = purchaseShop();
    $office = purchaseOffice('bookshop.manage');

    // No number set, switch on: no office SMS, and nothing fails.
    purchaseBuy($vendor, $book);
    expect(collect($sms->sent)->pluck(0)->all())->toBe(['7712345', '7000001']);

    // A number, but the office SMS switched off.
    test()->withoutLocalizationMiddleware()->actingAs($office)->post(route('admin.bookshop.notices.save'), [
        'customer_email' => 1, 'customer_sms' => 1, 'vendor_email' => 1, 'vendor_sms' => 1, 'office_email' => 1, 'office_sms' => 0, 'office_contact_phone' => '7009999',
    ])->assertSessionHasNoErrors();
    auth()->logout();
    $sms->sent = [];
    purchaseBuy($vendor, $book->refresh());
    expect(collect($sms->sent)->pluck(0)->all())->toBe(['7712345', '7000001']);

    // A wrong number is refused on the form.
    test()->withoutLocalizationMiddleware()->actingAs($office)->post(route('admin.bookshop.notices.save'), ['office_contact_phone' => 'call me'])->assertSessionHasErrors('office_contact_phone');
});

it('tells the reader, the writer and the office of a Digital Library sale', function () {
    Mail::fake();
    $sms = purchaseSms();
    $office = purchaseOffice('library.manage');
    test()->withoutLocalizationMiddleware()->actingAs($office)->put(route('admin.library.settings.update'), [
        'refund_window_days' => 7, 'default_writer_commission' => 70, 'min_payout' => 100, 'gift_card_min' => 50, 'gift_card_max' => 5000,
        'gift_card_expiry_months' => 0, 'research_reviews_required' => 1, 'payouts_enabled' => false,
        'notices_email' => true, 'notices_sms' => true, 'office_email' => 'library-office@example.test', 'office_phone' => '7008888',
    ])->assertSessionHasNoErrors();
    auth()->logout();

    $writerUser = User::factory()->create(['email' => 'writer@example.test', 'phone' => '7771234']);
    $writer = WriterProfile::query()->create(['user_id' => $writerUser->id, 'display_name' => 'Ustadh Ali', 'slug' => 'ustadh-ali', 'status' => 'active', 'approved_at' => now()]);
    $item = LibraryItem::query()->create(['title' => 'Seerah Notes', 'slug' => 'seerah-notes', 'content_type' => 'book', 'access_type' => 'paid', 'price' => 100, 'status' => 'published', 'writer_id' => $writer->id]);
    $reader = User::factory()->create(['email' => 'reader@example.test', 'phone' => '7715555']);
    app(CreditWalletAction::class)->execute($reader->id, 500, 'admin', null, 'Top-up');

    app(StartLibraryCheckoutAction::class)->execute($item->slug, $reader->id, null, null, true);

    expect(UserNotification::query()->where('user_id', $reader->id)->where('title', 'Your purchase is ready')->exists())->toBeTrue()
        ->and(UserNotification::query()->where('user_id', $writerUser->id)->where('title', 'New sale')->exists())->toBeTrue()
        ->and(UserNotification::query()->where('user_id', $office->id)->where('title', 'A Digital Library sale')->exists())->toBeTrue();
    Mail::assertQueued(LibraryNoticeMail::class, fn ($m) => $m->hasTo('reader@example.test'));
    Mail::assertQueued(LibraryNoticeMail::class, fn ($m) => $m->hasTo('writer@example.test'));
    Mail::assertQueued(LibraryNoticeMail::class, fn ($m) => $m->hasTo('library-office@example.test') && $m->heading === 'A Digital Library sale');
    expect(collect($sms->sent)->pluck(0)->sort()->values()->all())->toBe(['7008888', '7715555', '7771234']);
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['notice_office_paid_title', 'switch_office_sms', 'office_contact_hint'] as $key) {
            expect(__("shop.{$key}", [], $locale))->not->toBe(__("shop.{$key}", [], 'en'))->not->toBe("shop.{$key}");
        }
        expect(__('admin.library_settings_office_email', [], $locale))->not->toBe(__('admin.library_settings_office_email', [], 'en'));
    }
});
