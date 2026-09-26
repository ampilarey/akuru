<?php

use App\Domains\Bookshop\Actions\ShopOpenAction;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderReturn;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductImage;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Identity\Models\User;
use App\Domains\Library\Models\LibraryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * B11: the seven follow-ups the Bookstore audit (BOOKSHOP_PLAN §15) found
 * unbuilt — the whole shop closed by the office, a gift message through
 * the checkout, a printed book's e-book link, alt text on every photo,
 * the gallery lightbox, GST collected on the office's tax report and a
 * shop's returns rate.
 */
function followShop(string $slug = 'fitrah', array $overrides = []): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create($overrides + ['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active']);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'courier_male', 'name' => 'Courier', 'fee' => 30, 'handling_days' => 1, 'is_active' => true]);

    return [$vendor, $owner];
}

function followProduct(Vendor $vendor, string $title, float $price, array $overrides = []): Product
{
    return Product::query()->create($overrides + [
        'vendor_id' => $vendor->id, 'slug' => \Illuminate\Support\Str::slug($title), 'title' => $title, 'price' => $price,
        'currency' => 'MVR', 'tax_class' => 'standard', 'track_stock' => true, 'stock' => 10, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

function followAs(?User $user = null)
{
    $t = test()->withoutLocalizationMiddleware();
    if ($user === null) {
        app('auth')->forgetGuards();

        return $t;
    }

    return $t->actingAs($user);
}

function followOffice(): User
{
    Permission::findOrCreate('bookshop.manage', 'web');
    Role::findOrCreate('admin', 'web')->givePermissionTo('bookshop.manage');
    $office = User::factory()->create();
    $office->assignRole('admin');

    return $office;
}

function followCustomer(float $wallet = 2000): User
{
    $customer = User::factory()->create();
    app(CreditWalletAction::class)->execute($customer->id, $wallet, 'admin', null, 'Top-up');

    return $customer;
}

/** Buys from the wallet through the real checkout; the one order it made for the vendor. */
function followBuy(User $customer, Vendor $vendor, array $lines, array $extra = []): Order
{
    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    foreach ($lines as [$product, $quantity]) {
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity]);
    }
    $method = 'm'.VendorDeliveryMethod::query()->where('vendor_id', $vendor->id)->value('id');
    followAs($customer)->post(route('public.shop.checkout.store'), $extra + [
        'recipient_name' => 'Aishath', 'phone' => '7712345', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => [$vendor->slug => $method], 'payment_method' => 'wallet',
    ])->assertSessionHasNoErrors();

    return Order::query()->where('user_id', $customer->id)->where('vendor_id', $vendor->id)->orderByDesc('id')->firstOrFail();
}

function followDeliver(User $owner, Order $order): void
{
    foreach (['processing', 'dispatched', 'delivered'] as $step) {
        followAs($owner)->post(route('vendor.orders.advance', $order->id), ['to' => $step, 'carrier' => 'Bike', 'tracking_note' => 'x'])->assertSessionHasNoErrors();
    }
}

it('lets the office close the whole bookstore: visitors see the notice, customers keep their orders, the office and the shops carry on', function () {
    [$fitrah, $owner] = followShop();
    $book = followProduct($fitrah, 'Tracing Book', 100);
    $office = followOffice();
    $customer = followCustomer();
    $order = followBuy($customer, $fitrah, [[$book, 1]]);

    expect(app(ShopOpenAction::class)->isOpen())->toBeTrue();
    followAs()->get(route('public.shop.index'))->assertOk();
    followAs($owner)->post(route('admin.bookshop.open'), ['open' => 0])->assertForbidden();
    followAs($office)->post(route('admin.bookshop.open'), ['open' => 0, 'message' => 'Back after Eid, insha Allah.'])->assertSessionHasNoErrors();
    expect(app(ShopOpenAction::class)->isOpen())->toBeFalse()->and(app(ShopOpenAction::class)->message())->toBe('Back after Eid, insha Allah.');

    // Browsing, the product, the cart: the notice, as a 503 so nothing indexes it.
    followAs()->get(route('public.shop.index'))->assertStatus(503)->assertSee('data-testid="shop-closed"', false)->assertSee('Back after Eid, insha Allah.');
    followAs()->get(route('public.shop.product', 'tracing-book'))->assertStatus(503)->assertSee('data-testid="shop-closed"', false);
    followAs($customer)->get(route('public.shop.cart'))->assertStatus(503);
    // What the customer already has stays theirs.
    followAs($customer)->get(route('public.shop.orders'))->assertOk()->assertSee($order->number);
    followAs($customer)->get(route('public.shop.orders.show', $order->number))->assertOk();
    // The shop's portal and the office see the shop as usual.
    followAs($owner)->get(route('vendor.index'))->assertOk();
    followAs($office)->get(route('public.shop.index'))->assertOk();
    followAs($office)->get(route('admin.bookshop.index'))->assertInertia(fn ($page) => $page->where('shop_open.open', false)->where('shop_open.message', 'Back after Eid, insha Allah.'));

    followAs($office)->post(route('admin.bookshop.open'), ['open' => 1])->assertSessionHasNoErrors();
    followAs()->get(route('public.shop.index'))->assertOk()->assertDontSee('data-testid="shop-closed"', false);
    followAs($office)->post(route('admin.bookshop.open'), ['open' => 'maybe'])->assertSessionHasErrors('open');
});

it('carries a gift message from the checkout to the order, the packing slip and the shop, never past 300 characters', function () {
    [$fitrah, $owner] = followShop();
    $book = followProduct($fitrah, 'Tracing Book', 100);
    $customer = followCustomer();

    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => 1]);
    followAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath', 'phone' => '7712345', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => ['fitrah' => 'm'.VendorDeliveryMethod::query()->value('id')], 'payment_method' => 'wallet',
        'gift_message' => str_repeat('x', 301),
    ])->assertSessionHasErrors('gift_message');
    followAs($customer)->get(route('public.shop.checkout'))->assertOk()->assertSee('data-testid="gift-message"', false);

    $order = followBuy($customer, $fitrah, [[$book, 1]], ['gift_message' => '  Happy birthday, Hawwa! With love from Aishath.  ']);
    expect(BookshopCheckout::query()->latest('id')->value('gift_message'))->toBe('Happy birthday, Hawwa! With love from Aishath.')
        ->and($order->gift_message)->toBe('Happy birthday, Hawwa! With love from Aishath.');
    followAs($customer)->get(route('public.shop.orders.show', $order->number))->assertOk()->assertSee('data-testid="order-gift-message"', false)->assertSee('Happy birthday, Hawwa!');
    followAs($owner)->get(route('vendor.orders.index'))->assertInertia(fn ($page) => $page->where('orders.0.gift_message', 'Happy birthday, Hawwa! With love from Aishath.'));
    followAs($owner)->get(route('vendor.orders.print', $order->id))->assertInertia(fn ($page) => $page->where('order.gift_message', 'Happy birthday, Hawwa! With love from Aishath.'));

    // No message: nothing stored, nothing shown.
    $plain = followBuy($customer, $fitrah, [[$book, 1]]);
    expect($plain->gift_message)->toBeNull();
    followAs($customer)->get(route('public.shop.orders.show', $plain->number))->assertOk()->assertDontSee('data-testid="order-gift-message"', false);
});

it('shows a shop its returns rate and the office the GST the shops collected, in the report and its CSV', function () {
    [$fitrah, $owner] = followShop('fitrah', ['gst_registered' => true, 'tin' => '1234567GST501', 'legal_name' => 'Fitrah Pvt Ltd']);
    $book = followProduct($fitrah, 'Tracing Book', 100);
    $office = followOffice();
    $customer = followCustomer(3000);

    followAs($owner)->get(route('vendor.money.index'))->assertInertia(fn ($p) => $p->where('money.summary.returns_rate', null)->where('money.summary.delivered_orders', 0));

    $kept = followBuy($customer, $fitrah, [[$book, 1]]);
    $returned = followBuy($customer, $fitrah, [[$book, 1]]);
    $waiting = followBuy($customer, $fitrah, [[$book, 1]]);
    followDeliver($owner, $kept);
    followDeliver($owner, $returned);
    // Still nothing back: no returns.
    followAs($owner)->get(route('vendor.money.index'))->assertInertia(fn ($p) => $p->where('money.summary.returns_rate', '0.0')->where('money.summary.delivered_orders', 2)->where('money.summary.returned_orders', 0));

    $item = $returned->items()->firstOrFail();
    followAs($customer)->post(route('public.shop.orders.return', $returned->number), ['item_id' => $item->id, 'quantity' => 1, 'reason' => 'damaged', 'note' => 'Torn'])->assertSessionHasNoErrors();
    followAs($owner)->post(route('vendor.returns.decide', OrderReturn::query()->where('order_id', $returned->id)->value('id')), ['decision' => 'accept', 'restock' => 1])->assertSessionHasNoErrors();
    // One of two delivered orders had money go back; the undelivered one does not count.
    followAs($owner)->get(route('vendor.money.index'))->assertInertia(fn ($p) => $p->where('money.summary.returns_rate', '50.0')->where('money.summary.delivered_orders', 2)->where('money.summary.returned_orders', 1));

    // GST collected: 100 inclusive at 8% is 7.41 per order, three orders.
    expect((string) $kept->refresh()->tax)->toBe('7.41');
    followAs($office)->get(route('admin.bookshop.index'))->assertInertia(fn ($p) => $p->where('money.tax_report.0.orders', 3)->where('money.tax_report.0.sales_tax', '22.23'));
    $csv = followAs($office)->get(route('admin.bookshop.money.export', 'tax-report'))->assertOk()->streamedContent();
    expect($csv)->toContain('sales_gst')->toContain('22.23');
    expect($waiting->refresh()->status->value)->toBe('paid');
});

it('links a printed book to its Digital Library edition, wants a few words for every photo, and opens the gallery in a lightbox', function () {
    Storage::fake('public');
    [$fitrah, $owner] = followShop();
    Role::findOrCreate('admin', 'web');
    $published = LibraryItem::query()->create(['title' => 'Tracing Book (e-book)', 'slug' => 'tracing-book-ebook', 'content_type' => 'book', 'access_type' => 'free_public', 'status' => 'published', 'published_at' => now()]);
    $draft = LibraryItem::query()->create(['title' => 'Unfinished', 'slug' => 'unfinished', 'content_type' => 'book', 'access_type' => 'free_public', 'status' => 'draft']);

    $input = ['title' => 'Tracing Book', 'price' => '100.00', 'tax_class' => 'zero_rated', 'status' => 'active', 'visibility' => 'shop', 'stock' => 5, 'track_stock' => 1];
    followAs($owner)->get(route('vendor.index'))->assertInertia(fn ($page) => $page->where('options.library_items.0.title', 'Tracing Book (e-book)')->has('options.library_items', 1));
    followAs($owner)->post(route('vendor.products.store'), $input + ['library_item_id' => 999999])->assertSessionHasErrors('library_item_id');
    followAs($owner)->post(route('vendor.products.store'), $input + [
        'library_item_id' => $published->id,
        'photos' => [UploadedFile::fake()->image('front.jpg', 600, 600), UploadedFile::fake()->image('back.jpg', 600, 600)],
    ])->assertSessionHasNoErrors();
    $product = Product::query()->where('slug', 'tracing-book')->sole();
    expect($product->library_item_id)->toBe($published->id);

    // The product page: the e-book link, the title as alt text until the shop writes its own, the lightbox.
    followAs()->get(route('public.shop.product', 'tracing-book'))->assertOk()
        ->assertSee('data-testid="ebook-link"', false)->assertSee(route('public.library.show', 'tracing-book-ebook'))
        ->assertSee('alt="Tracing Book"', false)
        ->assertSee('data-testid="zoom-dialog"', false)->assertSee('data-zoom="1"', false);

    // Alt text: every photo on the form needs its words; they land on the photos and the page.
    $images = ProductImage::query()->where('product_id', $product->id)->orderBy('sort_order')->get();
    followAs($owner)->post(route('vendor.products.update', $product->id), $input + ['image_alts' => [$images[0]->id => '']])->assertSessionHasErrors('image_alts.'.$images[0]->id);
    followAs($owner)->post(route('vendor.products.update', $product->id), $input + ['image_alts' => [$images[0]->id => 'Front cover: a child tracing the letter alif', $images[1]->id => 'Back cover with the contents']])->assertSessionHasNoErrors();
    expect($images[0]->refresh()->alt_text)->toBe('Front cover: a child tracing the letter alif')->and($images[1]->refresh()->alt_text)->toBe('Back cover with the contents');
    followAs()->get(route('public.shop.product', 'tracing-book'))->assertOk()->assertSee('alt="Front cover: a child tracing the letter alif"', false);
    followAs($owner)->get(route('vendor.index'))->assertInertia(fn ($page) => $page->where('products.0.images.0.alt', 'Front cover: a child tracing the letter alif'));

    // Another shop's photo cannot be renamed from here.
    [$noor, $noorOwner] = followShop('noor');
    $noorProduct = followProduct($noor, 'Noor Book', 50);
    followAs($noorOwner)->post(route('vendor.products.update', $noorProduct->id), $input + ['title' => 'Noor Book', 'image_alts' => [$images[0]->id => 'Mine now']])->assertSessionHasNoErrors();
    expect($images[0]->refresh()->alt_text)->toBe('Front cover: a child tracing the letter alif');

    // A draft e-book, or none: no link.
    $product->update(['library_item_id' => $draft->id]);
    followAs()->get(route('public.shop.product', 'tracing-book'))->assertOk()->assertDontSee('data-testid="ebook-link"', false);
    followAs($owner)->post(route('vendor.products.update', $product->id), $input + ['library_item_id' => '', 'image_alts' => [$images[0]->id => 'Front', $images[1]->id => 'Back']])->assertSessionHasNoErrors();
    expect($product->refresh()->library_item_id)->toBeNull();
});
