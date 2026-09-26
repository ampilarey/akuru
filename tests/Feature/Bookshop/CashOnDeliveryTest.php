<?php

use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderRefund;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\StockMovement;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Models\VendorEarning;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Commerce\Actions\ListWalletAction;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN slice B9b, cash on delivery (decision 7): offered when the
 * office and every shop in the basket allow it, on the shop's own delivery
 * or collection, under the shop's cap; the stock goes at once and the order
 * never expires; the shop is paid in cash on delivery and then owes Akuru
 * the commission; a cancellation before delivery refunds nothing, a return
 * after it is refunded to the wallet and owed back by the shop.
 */
function codShop(string $slug = 'fitrah', array $overrides = []): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create($overrides + ['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active', 'commission_rate' => 10, 'cod_enabled' => true]);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'courier_male', 'name' => 'Courier', 'fee' => 30, 'handling_days' => 1, 'is_active' => true]);
    VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'boat', 'name' => 'Boat', 'fee' => 0, 'carrier_paid_on_arrival' => true, 'handling_days' => 3, 'is_active' => true]);

    return [$vendor, $owner];
}

function codProduct(Vendor $vendor, string $title, float $price, int $stock = 10): Product
{
    return Product::query()->create([
        'vendor_id' => $vendor->id, 'slug' => \Illuminate\Support\Str::slug($title), 'title' => $title, 'price' => $price,
        'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => $stock, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

function codAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

/** @param  list<array{0: Product, 1: int}>  $lines */
function codCheckout(User $customer, array $lines, string $kind = 'courier_male', string $method = 'cash_on_delivery')
{
    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    $delivery = [];
    foreach ($lines as [$product, $quantity]) {
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity]);
        $delivery[$product->vendor->slug] = 'm'.VendorDeliveryMethod::query()->where('vendor_id', $product->vendor_id)->where('kind', $kind)->value('id');
    }

    return codAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath', 'phone' => '7712345', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => $delivery, 'payment_method' => $method,
    ]);
}

function codAdvance(User $owner, Order $order, array $steps, array $extra = [])
{
    $last = null;
    foreach ($steps as $step) {
        $last = codAs($owner)->post(route('vendor.orders.advance', $order->id), ['to' => $step, 'carrier' => 'Bike', 'tracking_note' => 'x'] + ($step === 'delivered' ? $extra : []));
    }

    return $last;
}

it('places a cash order: offered, stock taken at once, no expiry, the shop and customer told', function () {
    [$fitrah, $owner] = codShop();
    $book = codProduct($fitrah, 'Tracing Book', 100, 5);
    $customer = User::factory()->create();

    CartItem::query()->create(['cart_id' => Cart::query()->firstOrCreate(['user_id' => $customer->id])->id, 'product_id' => $book->id, 'quantity' => 2]);
    codAs($customer)->get(route('public.shop.checkout'))->assertOk()->assertSee(__('shop.pay_cash_on_delivery'));
    Cart::query()->where('user_id', $customer->id)->first()?->items()->delete();

    codCheckout($customer, [[$book, 2]])->assertSessionHasNoErrors()->assertRedirect();
    $checkout = BookshopCheckout::query()->where('user_id', $customer->id)->sole();
    $order = Order::query()->where('user_id', $customer->id)->sole();
    expect($checkout->status->value)->toBe('cash_on_delivery')->and($order->status->value)->toBe('cash_due')->and($order->paid_at)->toBeNull()
        ->and((string) $order->total)->toBe('230.00')->and($book->refresh()->stock)->toBe(3)
        ->and(StockMovement::query()->where('order_id', $order->id)->value('kind'))->toBe('sale');
    expect(UserNotification::query()->where('user_id', $owner->id)->where('title', __('shop.notice_vendor_cod_order_title'))->exists())->toBeTrue();
    codAs($customer)->get(route('public.shop.checkout.status', $checkout->number))->assertOk()->assertSee(__('shop.cod_placed_heading'));

    // The expiry sweep leaves it alone.
    $checkout->forceFill(['expires_at' => now()->subHour()])->save();
    app(\App\Domains\Bookshop\Actions\Checkout\ExpireCheckoutsAction::class)->execute();
    expect($checkout->refresh()->status->value)->toBe('cash_on_delivery')->and($order->refresh()->status->value)->toBe('cash_due');
    codAs($owner)->get(route('vendor.orders.index', ['status' => 'cash_due']))->assertOk()->assertInertia(fn ($page) => $page->where('orders.0.number', $order->number)->where('orders.0.awaiting_cash', true)->where('counts.cash_due', 1));
});

it('is paid when the shop hands it over with the cash, and the shop then owes the commission', function () {
    [$fitrah, $owner] = codShop();
    $book = codProduct($fitrah, 'Tracing Book', 100);
    $customer = User::factory()->create();
    codCheckout($customer, [[$book, 2]])->assertSessionHasNoErrors();
    $order = Order::query()->where('user_id', $customer->id)->sole();

    codAdvance($owner, $order, ['processing', 'dispatched']);
    codAdvance($owner, $order, ['delivered'])->assertSessionHasErrors('cash_received');
    expect($order->refresh()->status->value)->toBe('dispatched')->and(VendorEarning::query()->count())->toBe(0);

    codAdvance($owner, $order, ['delivered'], ['cash_received' => 1])->assertSessionHasNoErrors();
    $order->refresh();
    expect($order->status->value)->toBe('delivered')->and($order->paid_at)->not->toBeNull()
        ->and($order->checkout->refresh()->status->value)->toBe('paid');
    $earning = VendorEarning::query()->where('order_id', $order->id)->sole();
    // Goods 200 + delivery 30 − 10% commission 20 = 210 owed to the shop; it holds 230 in cash, so it owes Akuru 20.
    expect((string) $earning->cash_collected)->toBe('230.00')->and((string) $earning->commission)->toBe('20.00')->and((string) $earning->net)->toBe('-20.00');
    codAs($owner)->get(route('vendor.money.index'))->assertOk()->assertInertia(fn ($page) => $page->where('money.earnings.0.cash_collected', '230.00'));
});

it('refunds nothing when a cash order is cancelled before delivery, and puts the stock back', function () {
    [$fitrah, $owner] = codShop();
    $book = codProduct($fitrah, 'Tracing Book', 100, 5);
    $customer = User::factory()->create();
    codCheckout($customer, [[$book, 1]])->assertSessionHasNoErrors();
    $order = Order::query()->where('user_id', $customer->id)->sole();

    codAs($customer)->post(route('public.shop.orders.cancel', $order->number), ['reason' => 'Changed my mind'])->assertSessionHasNoErrors();
    expect($order->refresh()->status->value)->toBe('cancelled')->and(OrderRefund::query()->count())->toBe(0)
        ->and($book->refresh()->stock)->toBe(5)->and(VendorEarning::query()->count())->toBe(0)
        ->and((float) app(ListWalletAction::class)->execute($customer->id)['balance'])->toBe(0.0);
});

it('refunds a returned cash order to the wallet, and the shop owes that back too', function () {
    [$fitrah, $owner] = codShop();
    $book = codProduct($fitrah, 'Tracing Book', 100);
    $customer = User::factory()->create();
    codCheckout($customer, [[$book, 2]])->assertSessionHasNoErrors();
    $order = Order::query()->where('user_id', $customer->id)->sole();
    codAdvance($owner, $order, ['processing', 'dispatched']);
    codAdvance($owner, $order, ['delivered'], ['cash_received' => 1]);

    $item = $order->items()->sole();
    codAs($customer)->post(route('public.shop.orders.return', $order->number), ['item_id' => $item->id, 'quantity' => 2, 'reason' => 'damaged', 'note' => 'Torn'])->assertSessionHasNoErrors();
    $return = \App\Domains\Bookshop\Models\OrderReturn::query()->sole();
    codAs($owner)->post(route('vendor.returns.decide', $return->id), ['decision' => 'accept', 'restock' => 0])->assertSessionHasNoErrors();

    $refund = OrderRefund::query()->sole();
    expect($refund->status->value)->toBe('done')->and($refund->destination)->toBe('wallet')->and((string) $refund->amount)->toBe('230.00');
    expect((float) app(ListWalletAction::class)->execute($customer->id)['balance'])->toBe(230.0);
    $earning = VendorEarning::query()->where('order_id', $order->id)->sole();
    // All of it went back: nothing earned, and the 230 the shop took is owed to Akuru.
    expect((string) $earning->net)->toBe('-230.00')->and($earning->status->value)->not->toBe('reversed');
});

it('is not offered, or is refused, where the office, the shop, the delivery or the cap say no', function () {
    [$fitrah, $owner] = codShop('fitrah', ['cod_max' => 150]);
    [$noor] = codShop('noor', ['cod_enabled' => false]);
    $book = codProduct($fitrah, 'Tracing Book', 100);
    $theirs = codProduct($noor, 'Their Book', 50);
    $customer = User::factory()->create();

    // Over the shop's cap (2 × 100 + 30 = 230 > 150).
    codCheckout($customer, [[$book, 2]])->assertSessionHasErrors('payment_method');
    Cart::query()->where('user_id', $customer->id)->first()->items()->delete();
    // By boat: the carrier takes no cash for the shop.
    codCheckout($customer, [[$book, 1]], 'boat')->assertSessionHasErrors('payment_method');
    Cart::query()->where('user_id', $customer->id)->first()->items()->delete();
    // A basket with a shop that takes no cash: not even offered.
    CartItem::query()->create(['cart_id' => Cart::query()->firstOrCreate(['user_id' => $customer->id])->id, 'product_id' => $theirs->id, 'quantity' => 1]);
    codAs($customer)->get(route('public.shop.checkout'))->assertOk()->assertDontSee(__('shop.pay_cash_on_delivery_hint'));
    Cart::query()->where('user_id', $customer->id)->first()->items()->delete();
    codCheckout($customer, [[$theirs, 1]])->assertSessionHasErrors('payment_method');
    expect(BookshopCheckout::query()->count())->toBe(0);

    // The office turns it off for everyone.
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('admin');
    $office->givePermissionTo('bookshop.manage');
    codAs($office)->post(route('admin.bookshop.cod'), ['on' => 0])->assertSessionHas('success');
    Cart::query()->where('user_id', $customer->id)->first()->items()->delete();
    codCheckout($customer, [[$book, 1]])->assertSessionHasErrors('payment_method');
    codAs($owner)->get(route('vendor.index'))->assertInertia(fn ($page) => $page->where('shop_settings.cod_office_on', false));

    // The shop's own settings.
    codAs($office)->post(route('admin.bookshop.cod'), ['on' => 1]);
    codAs($owner)->post(route('vendor.settings.save'), ['return_window_days' => 7, 'cod_enabled' => 0])->assertSessionHasNoErrors();
    expect($fitrah->refresh()->cod_enabled)->toBeFalse();
});
