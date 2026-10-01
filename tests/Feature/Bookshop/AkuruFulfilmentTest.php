<?php

use App\Domains\Bookshop\Actions\Checkout\ResolveDeliveryOptionsAction;
use App\Domains\Bookshop\Actions\Money\IssueCommissionInvoicesAction;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\StockMovement;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Models\VendorEarning;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * COMMERCE_PARITY_PLAN P6a — the owner: "inventory and delivery handled by
 * the vendor or by Akuru, with an extra charge when Akuru does it". A shop
 * set to Akuru: its customers see Akuru's courier (the fee is Akuru's), the
 * order lands in the office's queue (the shop only follows it), Akuru's
 * handling fee comes off the shop's earning and shows on its invoice, and
 * the stock handed to Akuru is drawn by sales and put back by cancellations.
 */
beforeEach(function () {
    Mail::fake();
});

function akuruWeb()
{
    return test()->withoutLocalizationMiddleware();
}

function akuruOffice(): User
{
    Role::findOrCreate('super_admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('super_admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

/** @return array{0: Vendor, 1: User, 2: Product} */
function akuruShop(array $overrides = []): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create($overrides + ['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active', 'commission_rate' => 10, 'fulfilment' => 'akuru', 'delivery_by' => 'akuru']);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'courier_male', 'name' => 'Our courier', 'fee' => 25, 'handling_days' => 1, 'is_active' => true]);
    VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'collect_vendor', 'name' => 'Collect from the shop', 'fee' => 0, 'handling_days' => 1, 'is_active' => true]);
    $book = Product::query()->create(['vendor_id' => $vendor->id, 'slug' => $vendor->slug.'-tracing-book', 'title' => 'Tracing Book', 'price' => 100, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => 10, 'status' => 'active', 'visibility' => 'shop']);

    return [$vendor, $owner, $book];
}

function akuruBuy(Vendor $vendor, Product $book, int $quantity, string $delivery): Order
{
    $customer = User::factory()->create(['phone' => '7712345']);
    app(CreditWalletAction::class)->execute($customer->id, 5000, 'admin', null, 'Top-up');
    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => $quantity]);
    akuruWeb()->actingAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath', 'phone' => '7712345', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => [$vendor->slug => $delivery], 'payment_method' => 'wallet',
    ])->assertSessionHasNoErrors();

    return Order::query()->where('user_id', $customer->id)->latest('id')->firstOrFail();
}

it('offers Akuru\'s courier and collection in place of the shop\'s own, at Akuru\'s fee', function () {
    [$vendor] = akuruShop();
    $options = collect(app(ResolveDeliveryOptionsAction::class)->execute($vendor, 100));

    expect($options->pluck('kind')->all())->toContain('akuru_courier', 'collect_akuru')
        ->not->toContain('courier_male', 'collect_vendor')
        ->and($options->firstWhere('kind', 'akuru_courier')['fee'])->toBe('30.00');

    // A shop that does its own is untouched.
    $vendor->update(['fulfilment' => 'vendor', 'delivery_by' => 'vendor']);
    expect(collect(app(ResolveDeliveryOptionsAction::class)->execute($vendor->fresh(), 100))->pluck('kind')->all())->toBe(['courier_male', 'collect_vendor']);
});

it('keeps the courier fee for Akuru, takes the handling fee off the shop, and puts it on the invoice', function () {
    [$vendor, , $book] = akuruShop();
    $order = akuruBuy($vendor, $book, 2, 'akuru');

    expect($order->fulfilled_by)->toBe('akuru')->and($order->delivery_revenue_to)->toBe('akuru')
        ->and((string) $order->akuru_handling_fee)->toBe('15.00')->and((string) $order->delivery_fee)->toBe('30.00');
    $earning = VendorEarning::query()->where('order_id', $order->id)->sole();
    // 200 of goods, 10% commission, no courier fee (Akuru's), 15 handling.
    expect((string) $earning->delivery_fee)->toBe('0.00')
        ->and((string) $earning->akuru_handling_fee)->toBe('15.00')
        ->and((string) $earning->net)->toBe('165.00');

    $invoice = app(IssueCommissionInvoicesAction::class)->execute(now())[0];
    expect((string) $invoice->handling)->toBe('15.00')->and((string) $invoice->total)->toBe('35.00');
});

it('puts the order in the office\'s queue, where the office moves it and the shop only follows it', function () {
    [$vendor, $owner, $book] = akuruShop();
    $office = akuruOffice();
    $order = akuruBuy($vendor, $book, 1, 'akuru');

    akuruWeb()->actingAs($office)->get(route('admin.bookshop.akuru'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Bookshop/Akuru')->where('orders.0.number', $order->number)->where('orders.0.next', ['processing', 'dispatched']));

    // The shop cannot move it, and its order page says Akuru has it.
    akuruWeb()->actingAs($owner)->post(route('vendor.orders.advance', $order->id), ['to' => 'processing'])->assertSessionHasErrors('status');
    akuruWeb()->actingAs($owner)->get(route('vendor.orders.index'))->assertInertia(fn ($page) => $page->where('orders.0.fulfilled_by', 'akuru')->where('orders.0.next', []));

    foreach (['processing', 'dispatched', 'delivered'] as $to) {
        akuruWeb()->actingAs($office)->post(route('admin.bookshop.akuru.advance', $order->id), ['to' => $to])->assertSessionHasNoErrors();
    }
    expect($order->fresh()->status->value)->toBe('delivered')
        ->and(UserNotification::query()->where('user_id', $order->user_id)->where('title', __('shop.notice_delivered_title', ['number' => $order->number]))->exists())->toBeTrue();

    // An order the shop packs is not the office's to move.
    [$own, , $ownBook] = akuruShop(['name' => 'Noor', 'slug' => 'noor', 'code' => 'NOR', 'fulfilment' => 'vendor', 'delivery_by' => 'vendor']);
    $ownOrder = akuruBuy($own, $ownBook, 1, 'm'.VendorDeliveryMethod::query()->where('vendor_id', $own->id)->where('kind', 'courier_male')->value('id'));
    expect($ownOrder->fulfilled_by)->toBe('vendor')->and((string) $ownOrder->akuru_handling_fee)->toBe('0.00');
    akuruWeb()->actingAs($office)->post(route('admin.bookshop.akuru.advance', $ownOrder->id), ['to' => 'processing'])->assertSessionHasErrors('status');
});

it('records the stock handed to Akuru, draws it on a sale and puts it back on a cancellation', function () {
    [$vendor, $owner, $book] = akuruShop();
    $office = akuruOffice();

    akuruWeb()->actingAs($office)->post(route('admin.bookshop.akuru.stock', $book->id), ['direction' => 'in', 'quantity' => 6])->assertSessionHasNoErrors();
    expect($book->fresh()->stock_at_akuru)->toBe(6)->and($book->fresh()->stock)->toBe(10)
        ->and(StockMovement::query()->where('product_id', $book->id)->where('kind', 'received_at_akuru')->value('quantity'))->toBe(6);
    // More than the shop has cannot be at Akuru.
    akuruWeb()->actingAs($office)->post(route('admin.bookshop.akuru.stock', $book->id), ['direction' => 'in', 'quantity' => 5])->assertSessionHasErrors('quantity');

    $order = akuruBuy($vendor, $book, 2, 'akuru');
    expect($book->fresh()->stock_at_akuru)->toBe(4);
    akuruWeb()->actingAs($owner)->get(route('vendor.index'))->assertInertia(fn ($page) => $page->where('products.0.stock_at_akuru', 4));

    // The customer cancels: back on Akuru's shelf, and no handling charged.
    akuruWeb()->actingAs(User::query()->find($order->user_id))->post(route('public.shop.orders.cancel', $order->number), ['reason' => 'Ordered twice'])->assertSessionHasNoErrors();
    expect($book->fresh()->stock_at_akuru)->toBe(6)
        ->and((string) VendorEarning::query()->where('order_id', $order->id)->value('akuru_handling_fee'))->toBe('0.00');

    akuruWeb()->actingAs($office)->post(route('admin.bookshop.akuru.stock', $book->id), ['direction' => 'out', 'quantity' => 6])->assertSessionHasNoErrors();
    expect($book->fresh()->stock_at_akuru)->toBe(0);
});

it('lets the office set the charges, a shop\'s own handling fee, and who packs and delivers', function () {
    [$vendor, , $book] = akuruShop(['fulfilment' => 'vendor', 'delivery_by' => 'vendor']);
    $office = akuruOffice();

    akuruWeb()->actingAs($office)->post(route('admin.bookshop.akuru.settings'), ['handling_fee' => 20, 'delivery_fee' => 35, 'delivery_free_over' => 500])->assertSessionHasNoErrors();
    akuruWeb()->actingAs($office)->put(route('admin.bookshop.vendors.update', $vendor->id), ['name' => 'Fitrah', 'status' => 'active', 'fulfilment' => 'akuru', 'delivery_by' => 'akuru', 'akuru_handling_fee' => 12.5])->assertSessionHasNoErrors();
    expect($vendor->fresh()->fulfilment)->toBe('akuru');

    $order = akuruBuy($vendor->fresh(), $book, 1, 'akuru');
    expect((string) $order->delivery_fee)->toBe('35.00')->and((string) $order->akuru_handling_fee)->toBe('12.50');

    $csv = akuruWeb()->actingAs($office)->get(route('admin.bookshop.akuru.export'))->streamedContent();
    expect($csv)->toContain($order->number)->toContain('1 × Tracing Book');
    akuruWeb()->actingAs(User::factory()->create())->get(route('admin.bookshop.akuru'))->assertForbidden();
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['akuru_page_title', 'akuru_courier_name', 'akuru_handling_line', 'akuru_packs_order', 'kind_akuru_courier'] as $key) {
            expect(__("shop.{$key}", [], $locale))->not->toBe(__("shop.{$key}", [], 'en'))->not->toBe("shop.{$key}");
        }
    }
});
