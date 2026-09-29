<?php

use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * STATUS §5ld, "Buy again": a customer puts one of their own orders' items
 * back in the cart in one tap, in the quantities ordered, at today's prices;
 * what can no longer be bought is named; someone else's order is not found.
 */
function againShop(): Vendor
{
    return Vendor::query()->create(['name' => 'Again Shop', 'slug' => 'again-shop', 'code' => 'AGN', 'status' => 'active']);
}

function againProduct(Vendor $vendor, string $title, float $price, array $overrides = []): Product
{
    return Product::query()->create($overrides + [
        'vendor_id' => $vendor->id, 'slug' => Str::slug($title), 'title' => $title, 'price' => $price,
        'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => 20, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

/** @param  list<array{0: Product, 1: int, 2?: ?ProductVariant}>  $lines */
function pastOrder(User $customer, Vendor $vendor, array $lines, string $number = 'AK-2026-000001-AGN'): Order
{
    $checkout = BookshopCheckout::query()->create(['number' => Str::before($number, '-AGN'), 'user_id' => $customer->id, 'status' => 'paid', 'payment_method' => 'wallet', 'address_snapshot' => ['name' => 'A'], 'subtotal' => 0, 'discount' => 0, 'delivery_total' => 0, 'total' => 0, 'currency' => 'MVR', 'paid_at' => now()]);
    $order = Order::query()->create(['number' => $number, 'bookshop_checkout_id' => $checkout->id, 'vendor_id' => $vendor->id, 'user_id' => $customer->id, 'status' => 'delivered', 'delivery_kind' => 'collect_vendor', 'delivery_name' => 'Collect', 'address_snapshot' => ['name' => 'A'], 'subtotal' => 0, 'total' => 0, 'currency' => 'MVR', 'paid_at' => now()]);
    foreach ($lines as $line) {
        [$product, $quantity] = $line;
        $variant = $line[2] ?? null;
        OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant?->id, 'title' => $product->title, 'variant_name' => $variant?->name, 'unit_price' => 1, 'quantity' => $quantity, 'line_total' => $quantity, 'tax_class' => 'zero_rated', 'tax_amount' => 0]);
    }

    return $order;
}

function againAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

it('puts an order\'s items back in the cart in one tap, at today\'s prices, and names what cannot go in', function () {
    $shop = againShop();
    $maths = againProduct($shop, 'Maths Workbook', 50);
    $bag = againProduct($shop, 'School Bag', 300);
    $blue = ProductVariant::query()->create(['product_id' => $bag->id, 'name' => 'Blue', 'price' => 320, 'stock' => 5, 'is_active' => true]);
    $retired = againProduct($shop, 'Old Atlas', 90);
    $customer = User::factory()->create();
    $order = pastOrder($customer, $shop, [[$maths, 2], [$bag, 1, $blue], [$retired, 1]]);
    $retired->update(['status' => 'archived']);
    $maths->update(['price' => 55]);

    againAs($customer)->get(route('public.shop.orders.show', $order->number))->assertOk()->assertSee('data-testid="buy-again"', false);
    againAs($customer)->get(route('public.shop.orders'))->assertOk()->assertSee('data-testid="buy-again-'.$order->number.'"', false);

    againAs($customer)->post(route('public.shop.orders.buy-again', $order->number))
        ->assertRedirect(route('public.shop.cart'))
        ->assertSessionHas('success', __('shop.buy_again_added_flash', ['count' => 2]))
        ->assertSessionHas('warning', __('shop.buy_again_skipped_flash', ['titles' => 'Old Atlas']));

    $items = Cart::query()->where('user_id', $customer->id)->sole()->items()->orderBy('id')->get();
    expect($items->map(fn ($i) => [$i->product_id, $i->product_variant_id, $i->quantity])->all())->toBe([[$maths->id, null, 2], [$bag->id, $blue->id, 1]]);
    // Today's price, not the order's.
    againAs($customer)->get(route('public.shop.cart'))->assertOk()->assertSee('MVR 55.00')->assertSee('MVR 110.00')->assertSee('MVR 320.00');
});

it('skips an option that is gone and stock that has run out', function () {
    $shop = againShop();
    $bag = againProduct($shop, 'School Bag', 300);
    $red = ProductVariant::query()->create(['product_id' => $bag->id, 'name' => 'Red', 'price' => 300, 'stock' => 5, 'is_active' => true]);
    $pens = againProduct($shop, 'Pens', 5, ['stock' => 1]);
    $customer = User::factory()->create();
    $order = pastOrder($customer, $shop, [[$bag, 1, $red], [$pens, 3]]);
    $red->update(['is_active' => false]);

    againAs($customer)->post(route('public.shop.orders.buy-again', $order->number))
        ->assertSessionHas('success', __('shop.buy_again_added_flash', ['count' => 0]))
        ->assertSessionHas('warning', __('shop.buy_again_skipped_flash', ['titles' => 'School Bag — Red, Pens']));
});

it('never finds someone else\'s order, and needs a sign-in', function () {
    $shop = againShop();
    $owner = User::factory()->create();
    $order = pastOrder($owner, $shop, [[againProduct($shop, 'Maths Workbook', 50), 1]]);

    againAs(User::factory()->create())->post(route('public.shop.orders.buy-again', $order->number))->assertNotFound();
    auth()->logout();
    test()->withoutLocalizationMiddleware()->post(route('public.shop.orders.buy-again', $order->number))->assertRedirect(route('login'));
    expect(Cart::query()->count())->toBe(0);
});
