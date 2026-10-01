<?php

use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\DeliveryDriver;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Contracts\PushSenderInterface;
use App\Domains\Notifications\Models\Device;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * COMMERCE_PARITY_PLAN P8b: push sending has run since #551 — every in-app
 * notice goes to the person's registered phones when a push driver is set.
 * What was missing was the notice itself: a driver was never told an order
 * was theirs. Now they are, and the one it was taken from is told too.
 */
beforeEach(function () {
    Mail::fake();
    config(['push.driver' => 'log']);
    $this->pushed = new class implements PushSenderInterface
    {
        public array $sent = [];

        public function sendToDevice(string $deviceToken, array $payload): bool
        {
            $this->sent[] = [$deviceToken, $payload['title'] ?? null];

            return true;
        }
    };
    app()->instance(PushSenderInterface::class, $this->pushed);
});

function noticeOffice(): User
{
    Role::findOrCreate('super_admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('super_admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

function noticeOrder(): Order
{
    $vendor = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active', 'fulfilment' => 'akuru', 'delivery_by' => 'akuru']);
    $book = Product::query()->create(['vendor_id' => $vendor->id, 'slug' => 'fitrah-book', 'title' => 'Tracing Book', 'price' => 100, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop']);
    $customer = User::factory()->create(['phone' => '7712345']);
    app(CreditWalletAction::class)->execute($customer->id, 1000, 'admin', null, 'Top-up');
    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => 1]);
    test()->withoutLocalizationMiddleware()->actingAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath', 'phone' => '7712345', 'atoll' => 'K', 'island' => 'Hulhumalé', 'street' => 'M. Example',
        'delivery' => [$vendor->slug => 'akuru'], 'payment_method' => 'wallet',
    ])->assertSessionHasNoErrors();

    return Order::query()->where('user_id', $customer->id)->latest('id')->firstOrFail();
}

function noticeDriver(User $office, string $email): DeliveryDriver
{
    test()->withoutLocalizationMiddleware()->actingAs($office)->post(route('admin.bookshop.akuru.drivers.store'), ['email' => $email, 'name' => 'Driver '.$email, 'phone' => '7701111'])->assertSessionHasNoErrors();

    return DeliveryDriver::query()->latest('id')->firstOrFail();
}

it('tells the driver an order is theirs, and the phone they registered gets it as a push', function () {
    $office = noticeOffice();
    $order = noticeOrder();
    $driver = noticeDriver($office, 'ali.driver@example.test');
    Device::query()->create(['user_id' => $driver->user_id, 'platform' => 'android', 'token' => 'ali-phone', 'is_active' => true]);

    test()->withoutLocalizationMiddleware()->actingAs($office)->post(route('admin.bookshop.akuru.assign', $order->id), ['driver_id' => $driver->id])->assertSessionHasNoErrors();

    $title = __('shop.notice_driver_assigned_title', ['number' => $order->number]);
    $notice = UserNotification::query()->where('user_id', $driver->user_id)->where('title', $title)->sole();
    expect($notice->message)->toContain('Hulhumalé')
        ->and($this->pushed->sent)->toContain(['ali-phone', $title]);
});

it('tells the driver an order was taken from them when the office gives it to another', function () {
    $office = noticeOffice();
    $order = noticeOrder();
    $ali = noticeDriver($office, 'ali.driver@example.test');
    $hassan = noticeDriver($office, 'hassan.driver@example.test');

    test()->withoutLocalizationMiddleware()->actingAs($office)->post(route('admin.bookshop.akuru.assign', $order->id), ['driver_id' => $ali->id]);
    test()->withoutLocalizationMiddleware()->actingAs($office)->post(route('admin.bookshop.akuru.assign', $order->id), ['driver_id' => $hassan->id])->assertSessionHasNoErrors();

    expect(UserNotification::query()->where('user_id', $ali->user_id)->where('title', __('shop.notice_driver_unassigned_title', ['number' => $order->number]))->exists())->toBeTrue()
        ->and(UserNotification::query()->where('user_id', $hassan->user_id)->where('title', __('shop.notice_driver_assigned_title', ['number' => $order->number]))->exists())->toBeTrue();
    // Giving it to the same driver again tells nobody it was taken.
    test()->withoutLocalizationMiddleware()->actingAs($office)->post(route('admin.bookshop.akuru.assign', $order->id), ['driver_id' => $hassan->id]);
    expect(UserNotification::query()->where('user_id', $hassan->user_id)->where('title', __('shop.notice_driver_unassigned_title', ['number' => $order->number]))->exists())->toBeFalse();
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['notice_driver_assigned_title', 'notice_driver_assigned_body', 'notice_driver_unassigned_title', 'notice_driver_unassigned_body'] as $key) {
            expect(__("shop.{$key}", [], $locale))->not->toBe(__("shop.{$key}", [], 'en'))->not->toBe("shop.{$key}");
        }
    }
});
