<?php

use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * COMMERCE_PARITY_PLAN P8d: pre-orders. A shop gives a product a release
 * date; until then it sells with nothing on the shelf, paid in full, and the
 * order ships from that date; on the date the buyer and the shop are told,
 * once.
 */
beforeEach(function () {
    Mail::fake();
    Role::findOrCreate('vendor', 'web');
    $this->vendor = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active']);
    $this->owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $this->vendor->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    $this->method = VendorDeliveryMethod::query()->create(['vendor_id' => $this->vendor->id, 'kind' => 'collect_vendor', 'name' => 'Collect', 'fee' => 0, 'handling_days' => 1, 'is_active' => true]);
    $this->release = now('Indian/Maldives')->addDays(10)->toDateString();
    $this->book = Product::query()->create(['vendor_id' => $this->vendor->id, 'slug' => 'grade-4-reader', 'title' => 'Grade 4 Reader', 'price' => 120, 'currency' => 'MVR', 'tax_class' => 'zero_rated',
        'track_stock' => true, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop', 'preorder_release_on' => $this->release]);
    $this->customer = User::factory()->create(['phone' => '7712345']);
    app(CreditWalletAction::class)->execute($this->customer->id, 1000, 'admin', null, 'Top-up');
});

function preWeb()
{
    return test()->withoutLocalizationMiddleware();
}

function preBuy(User $customer, Product $book, VendorDeliveryMethod $method, int $quantity = 1)
{
    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => $quantity]);

    return preWeb()->actingAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath', 'phone' => '7712345', 'delivery' => ['fitrah' => 'm'.$method->id], 'payment_method' => 'wallet',
    ]);
}

it('sells a pre-order with nothing on the shelf, says when it ships, and marks the order', function () {
    preWeb()->get(route('public.shop.product', 'grade-4-reader'))->assertOk()
        ->assertSee('data-stock="preorder"', false)->assertSee($this->release)->assertSee('data-testid="add-to-cart"', false);
    preWeb()->actingAs($this->customer)->post(route('public.shop.cart.add'), ['product' => 'grade-4-reader', 'quantity' => 2])->assertSessionHasNoErrors();
    preWeb()->actingAs($this->customer)->get(route('public.shop.cart'))->assertSee('data-testid="cart-preorder"', false);
    CartItem::query()->delete();

    preBuy($this->customer, $this->book, $this->method, 2)->assertSessionHasNoErrors();
    $order = Order::query()->sole();
    expect($order->ships_from->toDateString())->toBe($this->release)->and($order->paid_at)->not->toBeNull()->and((string) $order->total)->toBe('240.00');

    preWeb()->actingAs($this->customer)->get(route('public.shop.orders.show', $order->number))->assertSee('data-testid="order-preorder"', false)->assertSee($this->release);
    preWeb()->actingAs($this->owner)->get(route('vendor.orders.index'))->assertInertia(fn ($page) => $page->where('orders.0.ships_from', $this->release));
});

it('stops being a pre-order on its release date, and an ordinary sold-out product still cannot be bought', function () {
    $this->book->update(['preorder_release_on' => now('Indian/Maldives')->toDateString()]);
    preBuy($this->customer, $this->book->fresh(), $this->method)->assertSessionHasErrors('cart');
    expect(Order::query()->count())->toBe(0);
});

it('tells the buyer and the shop on the release date, once', function () {
    preBuy($this->customer, $this->book, $this->method)->assertSessionHasNoErrors();
    $order = Order::query()->sole();

    Artisan::call('bookshop:release-preorders');
    expect(UserNotification::query()->where('user_id', $this->customer->id)->where('title', __('shop.notice_preorder_released_title', ['number' => $order->number]))->exists())->toBeFalse();

    $this->travel(10)->days();
    Artisan::call('bookshop:release-preorders');
    Artisan::call('bookshop:release-preorders');
    expect(UserNotification::query()->where('user_id', $this->customer->id)->where('title', __('shop.notice_preorder_released_title', ['number' => $order->number]))->count())->toBe(1)
        ->and(UserNotification::query()->where('user_id', $this->owner->id)->where('title', __('shop.notice_preorder_vendor_title', ['number' => $order->number]))->count())->toBe(1)
        ->and($order->fresh()->preorder_released_at)->not->toBeNull();
});

it('lets the shop set a release date to come, and refuses one already past', function () {
    $product = ['title' => 'Grade 5 Reader', 'price' => 130, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => 1, 'stock' => 0, 'status' => 'draft', 'visibility' => 'shop'];
    preWeb()->actingAs($this->owner)->post(route('vendor.products.store'), $product + ['preorder_release_on' => now()->subDay()->toDateString()])->assertSessionHasErrors('preorder_release_on');
    preWeb()->actingAs($this->owner)->post(route('vendor.products.store'), $product + ['preorder_release_on' => $this->release])->assertSessionHasNoErrors();
    expect(Product::query()->where('title', 'Grade 5 Reader')->value('preorder_release_on'))->not->toBeNull();
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['stock_preorder', 'preorder_note', 'order_preorder', 'vendor_order_preorder', 'preorder_release_on', 'notice_preorder_released_title'] as $key) {
            expect(__("shop.{$key}", [], $locale))->not->toBe(__("shop.{$key}", [], 'en'))->not->toBe("shop.{$key}");
        }
    }
});
