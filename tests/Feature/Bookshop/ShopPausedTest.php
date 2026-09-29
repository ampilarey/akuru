<?php

use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * STATUS §5lo, a paused shop: the office stops it selling — its page, its
 * products, the cart, the listing and the API all lose it — but its people
 * keep the portal and finish the orders already paid. Suspended still shuts
 * the portal.
 */
function pausedSetup(): array
{
    Role::findOrCreate('vendor', 'web');
    Role::findOrCreate('super_admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('super_admin');
    $office->givePermissionTo('bookshop.manage');

    $vendor = Vendor::query()->create(['name' => 'Pause Shop', 'slug' => 'pause-shop', 'code' => 'PAU', 'status' => 'active']);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    $product = Product::query()->create(['vendor_id' => $vendor->id, 'slug' => 'pause-atlas', 'title' => 'Pause Atlas', 'price' => 90, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop']);

    $buyer = User::factory()->create();
    $checkout = BookshopCheckout::query()->create(['number' => 'AK-PAUSE-1', 'user_id' => $buyer->id, 'status' => 'paid', 'payment_method' => 'wallet', 'address_snapshot' => ['recipient_name' => 'A'], 'subtotal' => 90, 'discount' => 0, 'delivery_total' => 0, 'total' => 90, 'currency' => 'MVR', 'paid_at' => now()]);
    $order = Order::query()->create(['number' => 'AK-PAUSE-1-PAU', 'bookshop_checkout_id' => $checkout->id, 'vendor_id' => $vendor->id, 'user_id' => $buyer->id, 'status' => 'paid', 'delivery_kind' => 'courier_male', 'delivery_name' => 'Courier', 'address_snapshot' => ['recipient_name' => 'A'], 'subtotal' => 90, 'total' => 90, 'currency' => 'MVR', 'paid_at' => now()]);
    OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $product->id, 'title' => 'Pause Atlas', 'unit_price' => 90, 'quantity' => 1, 'line_total' => 90, 'tax_class' => 'zero_rated', 'tax_amount' => 0]);

    return [$office, $vendor, $owner, $product, $order, $buyer];
}

function pausedAs(?User $user = null)
{
    $t = test()->withoutLocalizationMiddleware();

    return $user ? $t->actingAs($user) : $t;
}

function setShopStatus(User $office, Vendor $vendor, string $status)
{
    return pausedAs($office)->put(route('admin.bookshop.vendors.update', $vendor->id), ['name' => $vendor->name, 'status' => $status]);
}

it('stops a paused shop selling everywhere a customer looks', function () {
    [$office, $vendor, , $product, , $buyer] = pausedSetup();
    pausedAs()->get(route('public.shop.product', $product->slug))->assertOk();

    setShopStatus($office, $vendor, 'paused')->assertSessionHasNoErrors();
    expect($vendor->refresh()->status->value)->toBe('paused');
    auth()->logout();

    pausedAs()->get(route('public.shop.product', $product->slug))->assertNotFound();
    pausedAs()->get(route('public.shop.vendor', $vendor->slug))->assertNotFound();
    pausedAs()->get(route('public.shop.index'))->assertDontSee('Pause Atlas');
    pausedAs()->getJson(route('api.bookstore.products'))->assertJsonPath('meta.total', 0);
    pausedAs($buyer)->post(route('public.shop.cart.add'), ['product' => $product->slug, 'quantity' => 1])->assertSessionHasErrors();

    // The buyer still sees the order they already paid for.
    pausedAs($buyer)->get(route('public.shop.orders.show', 'AK-PAUSE-1-PAU'))->assertOk();
});

it('keeps the portal open so the shop finishes its orders, with the reason at the top', function () {
    [$office, $vendor, $owner, , $order] = pausedSetup();
    setShopStatus($office, $vendor, 'paused');
    auth()->logout();

    pausedAs($owner)->get(route('vendor.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Bookshop/Vendor')->where('vendor.paused', true)->where('t.shop_paused_banner', __('shop.shop_paused_banner')));
    pausedAs($owner)->get(route('vendor.orders.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('vendor.paused', true)->has('orders', 1));
    pausedAs($owner)->post(route('vendor.orders.advance', $order->id), ['to' => 'processing'])->assertSessionHasNoErrors();
    expect($order->refresh()->status->value)->toBe('processing');

    // Back to active: selling again, and the banner gone.
    auth()->logout();
    setShopStatus($office, $vendor, 'active');
    auth()->logout();
    pausedAs($owner)->get(route('vendor.index'))->assertInertia(fn ($page) => $page->where('vendor.paused', false));
    pausedAs()->get(route('public.shop.product', 'pause-atlas'))->assertOk();
});

it('still shuts the portal for a suspended shop, and lists each status by name for the office', function () {
    [$office, $vendor, $owner] = pausedSetup();
    setShopStatus($office, $vendor, 'suspended');
    auth()->logout();
    pausedAs($owner)->get(route('vendor.orders.index'))->assertForbidden();

    setShopStatus($office, $vendor, 'resting')->assertSessionHasErrors('status');
    setShopStatus($office, $vendor, 'paused');
    pausedAs($office)->get(route('admin.bookshop.index'))
        ->assertInertia(fn ($page) => $page->where('t.paused', __('shop.paused'))->where('vendors.0.status', 'paused'));
});
