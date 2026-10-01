<?php

use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\DeliveryDriver;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderDelivery;
use App\Domains\Bookshop\Models\OrderEvent;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
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
 * COMMERCE_PARITY_PLAN P6b: Akuru's drivers. The office adds a driver and
 * gives them an order Akuru's courier carries; the driver marks it picked up
 * (dispatched — the customer is told it is out for delivery) and delivered
 * with a photo (only the office and that driver can open it). A shop that
 * packs its own still starts the order; Akuru takes it from the door.
 */
beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
});

function driveWeb()
{
    return test()->withoutLocalizationMiddleware();
}

function driveOffice(): User
{
    Role::findOrCreate('super_admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('super_admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

/** @return array{0: Vendor, 1: User, 2: Product} */
function driveShop(string $fulfilment = 'akuru'): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah-'.$fulfilment, 'code' => strtoupper(substr($fulfilment, 0, 3)), 'status' => 'active', 'fulfilment' => $fulfilment, 'delivery_by' => 'akuru']);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    $book = Product::query()->create(['vendor_id' => $vendor->id, 'slug' => $vendor->slug.'-book', 'title' => 'Tracing Book', 'price' => 100, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop']);

    return [$vendor, $owner, $book];
}

function driveOrder(Vendor $vendor, Product $book): Order
{
    $customer = User::factory()->create(['phone' => '7712345']);
    app(CreditWalletAction::class)->execute($customer->id, 5000, 'admin', null, 'Top-up');
    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => 1]);
    driveWeb()->actingAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath', 'phone' => '7712345', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => [$vendor->slug => 'akuru'], 'payment_method' => 'wallet',
    ])->assertSessionHasNoErrors();

    return Order::query()->where('user_id', $customer->id)->latest('id')->firstOrFail();
}

function driveDriver(User $office, string $email = 'ali.driver@example.test'): array
{
    driveWeb()->actingAs($office)->post(route('admin.bookshop.akuru.drivers.store'), ['email' => $email, 'name' => 'Ali', 'phone' => '7701111'])->assertSessionHasNoErrors();
    $driver = DeliveryDriver::query()->latest('id')->firstOrFail();

    return [$driver, User::query()->findOrFail($driver->user_id)];
}

it('lets the office add a driver, who lands on their deliveries and is given an order', function () {
    [$vendor, , $book] = driveShop();
    $office = driveOffice();
    $order = driveOrder($vendor, $book);

    $response = driveWeb()->actingAs($office)->post(route('admin.bookshop.akuru.drivers.store'), ['email' => 'ali.driver@example.test', 'name' => 'Ali', 'phone' => '7701111']);
    $response->assertSessionHasNoErrors()->assertSessionHas('driver_added', fn ($a) => $a['temporary_password'] !== null);
    $driver = DeliveryDriver::query()->sole();
    $driverUser = User::query()->findOrFail($driver->user_id);
    expect($driverUser->hasRole('driver'))->toBeTrue();

    driveWeb()->actingAs($office)->post(route('admin.bookshop.akuru.assign', $order->id), ['driver_id' => $driver->id])->assertSessionHasNoErrors();
    driveWeb()->actingAs($office)->get(route('admin.bookshop.akuru'))
        ->assertInertia(fn ($page) => $page->where('orders.0.delivery_by_driver.driver', 'Ali')->where('drivers.0.open', 1));

    driveWeb()->actingAs($driverUser)->get(route('deliveries.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Bookshop/Deliveries')->where('deliveries.0.number', $order->number)->where('deliveries.0.recipient', 'Aishath'));
    // A customer is not a driver.
    driveWeb()->actingAs(User::query()->find($order->user_id))->get(route('deliveries.index'))->assertForbidden();
});

it('takes an order from picked up to delivered with a photo, and tells the customer at each step', function () {
    [$vendor, , $book] = driveShop();
    $office = driveOffice();
    $order = driveOrder($vendor, $book);
    [$driver, $driverUser] = driveDriver($office);
    driveWeb()->actingAs($office)->post(route('admin.bookshop.akuru.assign', $order->id), ['driver_id' => $driver->id]);
    $delivery = OrderDelivery::query()->sole();

    // Another driver cannot touch it.
    [, $other] = driveDriver($office, 'hassan.driver@example.test');
    driveWeb()->actingAs($other)->post(route('deliveries.picked-up', $delivery->id))->assertNotFound();
    // Delivered before picked up is refused.
    driveWeb()->actingAs($driverUser)->post(route('deliveries.delivered', $delivery->id), ['photo' => UploadedFile::fake()->image('door.jpg')])->assertSessionHasErrors('photo');

    driveWeb()->actingAs($driverUser)->post(route('deliveries.picked-up', $delivery->id))->assertSessionHasNoErrors();
    $order->refresh();
    expect($order->status->value)->toBe('dispatched')->and($order->tracking_note)->toBe(__('shop.out_for_delivery'))->and($order->carrier)->toBe('Akuru courier: Ali')
        ->and(UserNotification::query()->where('user_id', $order->user_id)->where('title', __('shop.notice_dispatched_title', ['number' => $order->number]))->exists())->toBeTrue();
    // Once picked up, the office cannot hand it to someone else.
    driveWeb()->actingAs($office)->post(route('admin.bookshop.akuru.assign', $order->id), ['driver_id' => DeliveryDriver::query()->where('user_id', $other->id)->value('id')])->assertSessionHasErrors('driver');

    driveWeb()->actingAs($driverUser)->post(route('deliveries.delivered', $delivery->id))->assertSessionHasErrors('photo');
    driveWeb()->actingAs($driverUser)->post(route('deliveries.delivered', $delivery->id), ['photo' => UploadedFile::fake()->image('door.jpg', 800, 600), 'note' => 'Left with the guard'])->assertSessionHasNoErrors();
    expect($order->fresh()->status->value)->toBe('delivered')
        ->and(OrderEvent::query()->where('order_id', $order->id)->where('type', 'delivered_by_driver')->value('note'))->toBe('Ali')
        ->and(UserNotification::query()->where('user_id', $order->user_id)->where('title', __('shop.notice_delivered_title', ['number' => $order->number]))->exists())->toBeTrue();

    // The photo: the office and this driver, nobody else.
    driveWeb()->actingAs($office)->get(route('deliveries.proof', $delivery->id))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    driveWeb()->actingAs($driverUser)->get(route('deliveries.proof', $delivery->id))->assertOk();
    driveWeb()->actingAs($other)->get(route('deliveries.proof', $delivery->id))->assertForbidden();
    driveWeb()->actingAs(User::query()->find($order->user_id))->get(route('deliveries.proof', $delivery->id))->assertForbidden();
});

it('lets a shop that packs its own start the order, and Akuru\'s driver take it from the door', function () {
    [$vendor, $owner, $book] = driveShop('vendor');
    $office = driveOffice();
    $order = driveOrder($vendor, $book);
    expect($order->fulfilled_by)->toBe('vendor')->and($order->delivery_kind->value)->toBe('akuru_courier');

    driveWeb()->actingAs($owner)->get(route('vendor.orders.index'))->assertInertia(fn ($page) => $page->where('orders.0.next', ['processing'])->where('orders.0.akuru_delivers', true));
    driveWeb()->actingAs($owner)->post(route('vendor.orders.advance', $order->id), ['to' => 'dispatched'])->assertSessionHasErrors('status');
    driveWeb()->actingAs($owner)->post(route('vendor.orders.advance', $order->id), ['to' => 'processing'])->assertSessionHasNoErrors();

    [$driver, $driverUser] = driveDriver($office);
    driveWeb()->actingAs($office)->post(route('admin.bookshop.akuru.assign', $order->id), ['driver_id' => $driver->id])->assertSessionHasNoErrors();
    driveWeb()->actingAs($driverUser)->post(route('deliveries.picked-up', OrderDelivery::query()->value('id')))->assertSessionHasNoErrors();
    expect($order->fresh()->status->value)->toBe('dispatched');
});

it('gives a driver only an order Akuru\'s courier carries, and only an active driver', function () {
    [$vendor, , $book] = driveShop();
    $office = driveOffice();
    $order = driveOrder($vendor, $book);
    [$driver] = driveDriver($office);

    driveWeb()->actingAs($office)->post(route('admin.bookshop.akuru.drivers.update', $driver->id), ['active' => 0])->assertSessionHasNoErrors();
    driveWeb()->actingAs($office)->post(route('admin.bookshop.akuru.assign', $order->id), ['driver_id' => $driver->id])->assertNotFound();

    $driver->update(['is_active' => true]);
    $order->forceFill(['delivery_kind' => 'collect_akuru'])->save();
    driveWeb()->actingAs($office)->post(route('admin.bookshop.akuru.assign', $order->id), ['driver_id' => $driver->id])->assertSessionHasErrors('driver');
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['deliveries_title', 'out_for_delivery', 'driver_picked_up', 'driver_delivered', 'drivers'] as $key) {
            expect(__("shop.{$key}", [], $locale))->not->toBe(__("shop.{$key}", [], 'en'))->not->toBe("shop.{$key}");
        }
        expect(__('roles.driver', [], $locale))->not->toBe(__('roles.driver', [], 'en'));
    }
});
