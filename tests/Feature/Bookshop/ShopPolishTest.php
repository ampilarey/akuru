<?php

use App\Domains\Bookshop\Actions\ManageShopHomeAction;
use App\Domains\Bookshop\Actions\Shop\PresentShopHomeAction;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductReview;
use App\Domains\Bookshop\Models\StockAlert;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorCollection;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Models\VendorEarning;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Bookshop\Models\WishlistItem;
use App\Domains\Bookshop\Support\Merchandise;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Actions\ResolveDiscountAction;
use App\Domains\Commerce\Models\DiscountCode;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => Merchandise::forget());

/**
 * BOOKSHOP_PLAN slice B7, shop polish and trust: search suggestions,
 * best-selling and top-rated sorts, badges, wishlist, recently viewed,
 * back-in-stock notices, reviews with vendor replies and office moderation,
 * vendor-funded codes scoped to the vendor's goods, free delivery over an
 * amount, and the office's shop-home merchandising.
 */
function polishShop(string $slug = 'fitrah', array $overrides = []): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create($overrides + ['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active']);
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $staff->id, 'role' => 'staff', 'agreement_accepted_at' => now()]);
    VendorDeliveryMethod::query()->create(['vendor_id' => $vendor->id, 'kind' => 'courier_male', 'name' => 'Courier', 'fee' => 30, 'handling_days' => 1, 'is_active' => true]);

    return [$vendor, $owner, $staff];
}

function polishProduct(Vendor $vendor, string $title, float $price, int $stock = 10, array $overrides = []): Product
{
    $createdAt = $overrides['created_at'] ?? null;
    unset($overrides['created_at']);
    $product = Product::query()->create($overrides + [
        'vendor_id' => $vendor->id, 'slug' => \Illuminate\Support\Str::slug($title), 'title' => $title, 'price' => $price,
        'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => $stock, 'status' => 'active', 'visibility' => 'shop',
    ]);
    if ($createdAt !== null) {
        $product->forceFill(['created_at' => $createdAt])->saveQuietly();
    }

    return $product;
}

function polishAs(?User $user = null)
{
    $t = test()->withoutLocalizationMiddleware();
    if ($user === null) {
        // A guest: forget whoever an earlier request in the test signed in.
        app('auth')->forgetGuards();

        return $t;
    }

    return $t->actingAs($user);
}

function polishCustomer(string $name = 'Aishath Mohamed'): User
{
    $customer = User::factory()->create(['name' => $name]);
    app(CreditWalletAction::class)->execute($customer->id, 3000, 'admin', null, 'Top-up');

    return $customer;
}

/** @param  list<array{0: Product, 1: int}>  $lines */
function polishCheckout(User $customer, array $lines, ?string $code = null)
{
    $cart = Cart::query()->firstOrCreate(['user_id' => $customer->id]);
    $delivery = [];
    foreach ($lines as [$product, $quantity]) {
        CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity]);
        $vendor = $product->vendor;
        $delivery[$vendor->slug] = 'm'.VendorDeliveryMethod::query()->where('vendor_id', $vendor->id)->value('id');
    }

    return polishAs($customer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath', 'phone' => '7712345', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => $delivery, 'payment_method' => 'wallet', 'discount_code' => $code,
    ]);
}

function polishDeliver(User $owner, Order $order): void
{
    foreach (['processing', 'dispatched', 'delivered'] as $step) {
        polishAs($owner)->post(route('vendor.orders.advance', $order->id), ['to' => $step, 'carrier' => 'Bike', 'tracking_note' => 'x'])->assertSessionHasNoErrors();
    }
}

function polishOffice(): User
{
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

it('sorts by best selling and top rated, and shows sale, bestseller, new and the vendor\'s own badges', function () {
    [$fitrah] = polishShop();
    $quiet = polishProduct($fitrah, 'Quiet Book', 50, 10, ['created_at' => now()->subDays(60)]);
    $popular = polishProduct($fitrah, 'Popular Book', 50, 10, ['created_at' => now()->subDays(60), 'compare_at_price' => 80, 'badge' => 'Signed copy']);
    $fresh = polishProduct($fitrah, 'Fresh Book', 50);

    polishCheckout(polishCustomer(), [[$popular, 3]])->assertSessionHasNoErrors();
    Merchandise::forget();

    $best = polishAs()->get(route('public.shop.index', ['sort' => 'best_selling', 'q' => 'Book']))->assertOk();
    expect(strpos($best->getContent(), 'Popular Book'))->toBeLessThan(strpos($best->getContent(), 'Quiet Book'));

    $quiet->update(['rating_avg' => 4.5, 'rating_count' => 2]);
    $rated = polishAs()->get(route('public.shop.index', ['sort' => 'top_rated', 'q' => 'Book']))->assertOk();
    expect(strpos($rated->getContent(), 'Quiet Book'))->toBeLessThan(strpos($rated->getContent(), 'Fresh Book'));

    $kinds = fn (Product $p) => array_column(Merchandise::badges($p->refresh()), 'kind');
    expect($kinds($popular))->toBe(['custom', 'sale', 'bestseller'])
        ->and($kinds($fresh))->toBe(['new'])
        ->and($kinds($quiet))->toBe([]);
    expect(Merchandise::badges($popular)[0]['label'])->toBe('Signed copy');
    polishAs()->get(route('public.shop.product', $popular->slug))->assertOk()->assertSee('Signed copy');
});

it('suggests products, shops and categories as JSON, public catalogue only', function () {
    [$fitrah] = polishShop();
    polishProduct($fitrah, 'Arabic Tracing Book', 60);
    polishProduct($fitrah, 'Arabic Hidden Draft', 60, 10, ['status' => 'draft']);
    \App\Domains\Bookshop\Models\ProductCategory::query()->create(['name' => 'Arabic workbooks', 'slug' => 'arabic-workbooks']);

    $json = polishAs()->getJson(route('public.shop.suggest', ['q' => 'arab']))->assertOk()->json();
    expect(array_column($json['products'], 'title'))->toBe(['Arabic Tracing Book'])
        ->and(array_column($json['categories'], 'name'))->toBe(['Arabic workbooks']);
    expect(polishAs()->getJson(route('public.shop.suggest', ['q' => 'fit']))->json('vendors.0.name'))->toBe('Fitrah');
    expect(polishAs()->getJson(route('public.shop.suggest', ['q' => 'a']))->json('products'))->toBe([]);
});

it('keeps a signed-in customer\'s wishlist, with a CSV, and remembers what a visitor viewed', function () {
    [$fitrah] = polishShop();
    $a = polishProduct($fitrah, 'First Book', 40);
    $b = polishProduct($fitrah, 'Second Book', 45);
    $customer = polishCustomer();

    polishAs()->post(route('public.shop.wishlist.toggle', $a->slug))->assertRedirect();
    expect(WishlistItem::query()->count())->toBe(0);

    polishAs($customer)->post(route('public.shop.wishlist.toggle', $a->slug))->assertRedirect()->assertSessionHas('success');
    expect(WishlistItem::query()->where('user_id', $customer->id)->pluck('product_id')->all())->toBe([$a->id]);
    polishAs($customer)->get(route('public.shop.wishlist'))->assertOk()->assertSee('First Book')->assertDontSee('Second Book');
    $csv = polishAs($customer)->get(route('public.shop.wishlist.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('First Book')->toContain('in_stock');
    // Another person's wishlist is theirs alone.
    polishAs(polishCustomer('Mariyam Ali'))->get(route('public.shop.wishlist'))->assertOk()->assertDontSee('First Book');
    // A second press takes it off.
    polishAs($customer)->post(route('public.shop.wishlist.toggle', $a->slug));
    expect(WishlistItem::query()->count())->toBe(0);

    polishAs()->get(route('public.shop.product', $a->slug))->assertOk();
    polishAs()->get(route('public.shop.product', $b->slug))->assertOk()->assertSee('First Book');
    expect(session('bookshop.recently_viewed'))->toBe([$b->id, $a->id]);
    polishAs()->get(route('public.shop.index'))->assertOk()->assertSee(__('shop.recently_viewed'));
});

it('tells the people waiting once when a sold-out product is back, from a vendor\'s save', function () {
    [$fitrah, $owner] = polishShop();
    $gone = polishProduct($fitrah, 'Sold Out Book', 40, 0);
    $waiting = polishCustomer();

    polishAs($waiting)->get(route('public.shop.product', $gone->slug))->assertOk()->assertSee(__('shop.notify_me'));
    polishAs($waiting)->post(route('public.shop.stock-alert', $gone->slug))->assertRedirect()->assertSessionHas('success');
    expect(StockAlert::query()->where('user_id', $waiting->id)->whereNull('notified_at')->count())->toBe(1);

    // An in-stock product refuses the notice: there is nothing to wait for.
    $plenty = polishProduct($fitrah, 'Plenty Book', 40, 5);
    polishAs($waiting)->post(route('public.shop.stock-alert', $plenty->slug))->assertSessionHasErrors();

    polishAs($owner)->post(route('vendor.products.update', $gone->id), [
        'title' => 'Sold Out Book', 'price' => '40.00', 'tax_class' => 'zero_rated', 'status' => 'active', 'visibility' => 'shop', 'stock' => 4, 'track_stock' => 1,
    ])->assertSessionHasNoErrors();
    expect(StockAlert::query()->where('user_id', $waiting->id)->value('notified_at'))->not->toBeNull();
    // Told once: a second restock tells nobody again.
    expect(app(\App\Domains\Bookshop\Actions\Shop\CustomerListsAction::class)->notifyIfBack($gone->id))->toBe(0);
});

it('lets only a customer whose order was delivered review it once, the vendor reply, the office hide with a note', function () {
    [$fitrah, $owner, $staff] = polishShop();
    $book = polishProduct($fitrah, 'Reviewed Book', 60);
    $buyer = polishCustomer('Aishath Mohamed');
    $stranger = polishCustomer('Hassan Ali');

    polishCheckout($buyer, [[$book, 1]])->assertSessionHasNoErrors();
    $order = Order::query()->where('user_id', $buyer->id)->firstOrFail();

    // Not yet delivered: no review.
    polishAs($buyer)->post(route('public.shop.review', $book->slug), ['rating' => 5, 'body' => 'Early'])->assertSessionHasErrors('rating');
    polishAs($stranger)->post(route('public.shop.review', $book->slug), ['rating' => 1, 'body' => 'Never bought'])->assertSessionHasErrors('rating');

    polishDeliver($owner, $order);
    polishAs($buyer)->get(route('public.shop.orders.show', $order->number))->assertOk()->assertSee(__('shop.write_review'));
    polishAs($buyer)->post(route('public.shop.review', $book->slug), ['rating' => 4, 'body' => 'Clear letters, good paper.'])->assertRedirect()->assertSessionHas('success');
    polishAs($buyer)->post(route('public.shop.review', $book->slug), ['rating' => 5])->assertSessionHasErrors('rating');

    $review = ProductReview::query()->sole();
    expect($review->status)->toBe('published')->and($review->order_id)->toBe($order->id);
    expect((string) $book->refresh()->rating_avg)->toBe('4.00')->and($book->rating_count)->toBe(1);
    polishAs()->get(route('public.shop.product', $book->slug))->assertOk()->assertSee('Clear letters, good paper.')->assertSee('Aishath M.')->assertDontSee('Aishath Mohamed');

    // The shop's members read and reply; another shop cannot.
    polishAs($staff)->get(route('vendor.reviews.index'))->assertOk();
    polishAs($staff)->post(route('vendor.reviews.reply', $review->id), ['reply' => 'Thank you!'])->assertSessionHasNoErrors();
    [, $otherOwner] = polishShop('noor');
    polishAs($otherOwner)->post(route('vendor.reviews.reply', $review->id), ['reply' => 'Mine now'])->assertNotFound();
    expect($review->refresh()->vendor_reply)->toBe('Thank you!');
    expect(polishAs($owner)->get(route('vendor.reviews.export'))->assertOk()->streamedContent())->toContain('Clear letters');
    polishAs()->get(route('public.shop.product', $book->slug))->assertSee('Thank you!');

    // The office hides it, with a note; the rating follows.
    $office = polishOffice();
    polishAs($office)->post(route('admin.bookshop.reviews.moderate', $review->id), ['action' => 'hide'])->assertSessionHasErrors('note');
    polishAs($office)->post(route('admin.bookshop.reviews.moderate', $review->id), ['action' => 'hide', 'note' => 'Personal details'])->assertSessionHasNoErrors();
    expect($review->refresh()->status)->toBe('hidden')->and($review->moderation_note)->toBe('Personal details');
    expect($book->refresh()->rating_count)->toBe(0);
    polishAs()->get(route('public.shop.product', $book->slug))->assertDontSee('Clear letters, good paper.');
    polishAs($buyer)->post(route('admin.bookshop.reviews.moderate', $review->id), ['action' => 'publish'])->assertForbidden();
});

it('holds reviews for the office when premoderation is on', function () {
    config(['bookshop.reviews.premoderate' => true]);
    [$fitrah, $owner] = polishShop();
    $book = polishProduct($fitrah, 'Held Book', 60);
    $buyer = polishCustomer();
    polishCheckout($buyer, [[$book, 1]])->assertSessionHasNoErrors();
    polishDeliver($owner, Order::query()->where('user_id', $buyer->id)->firstOrFail());

    polishAs($buyer)->post(route('public.shop.review', $book->slug), ['rating' => 5, 'body' => 'Waiting for the office'])->assertSessionHas('success');
    expect(ProductReview::query()->value('status'))->toBe('pending')->and($book->refresh()->rating_count)->toBe(0);
    polishAs()->get(route('public.shop.product', $book->slug))->assertDontSee('Waiting for the office');

    polishAs(polishOffice())->post(route('admin.bookshop.reviews.moderate', ProductReview::query()->value('id')), ['action' => 'publish'])->assertSessionHasNoErrors();
    expect($book->refresh()->rating_count)->toBe(1);
    polishAs()->get(route('public.shop.product', $book->slug))->assertSee('Waiting for the office');
});

it('lets an owner make a code funded by the shop that takes only its goods off and comes off its earning', function () {
    [$fitrah, $owner, $staff] = polishShop('fitrah', ['commission_rate' => 10]);
    [$noor] = polishShop('noor');
    $ours = polishProduct($fitrah, 'Our Book', 100);
    $theirs = polishProduct($noor, 'Their Book', 200);

    polishAs($staff)->post(route('vendor.discount-codes.store'), ['code' => 'FIT10', 'discount_type' => 'percentage', 'discount_value' => 10])->assertForbidden();
    polishAs($owner)->post(route('vendor.discount-codes.store'), ['code' => 'fit95', 'discount_type' => 'percentage', 'discount_value' => 95])->assertSessionHasErrors('discount_value');
    polishAs($owner)->post(route('vendor.discount-codes.store'), ['code' => 'fit10', 'discount_type' => 'percentage', 'discount_value' => 10, 'per_user_limit' => 2])->assertSessionHasNoErrors();
    $code = DiscountCode::query()->where('code', 'FIT10')->sole();
    expect($code->applies_to_type)->toBe('vendor')->and($code->applies_to_id)->toBe($fitrah->id)
        ->and($code->discount_funding_source)->toBe('vendor')->and($code->created_by)->toBe($owner->id);
    polishAs($owner)->get(route('vendor.index'))->assertOk()->assertInertia(fn ($page) => $page->where('discount_codes.0.code', 'FIT10'));

    // Only Noor's goods in the basket: the code belongs to another shop.
    polishCheckout(polishCustomer(), [[$theirs, 1]], 'FIT10')->assertSessionHasErrors('discount_code');

    // Both shops: 10% of Fitrah's 100 only, borne by Fitrah's order alone.
    $customer = polishCustomer();
    polishCheckout($customer, [[$ours, 1], [$theirs, 1]], 'FIT10')->assertSessionHasNoErrors();
    $fitOrder = Order::query()->where('user_id', $customer->id)->where('vendor_id', $fitrah->id)->sole();
    $noorOrder = Order::query()->where('user_id', $customer->id)->where('vendor_id', $noor->id)->sole();
    expect((string) $fitOrder->discount)->toBe('10.00')->and((string) $noorOrder->discount)->toBe('0.00');
    $earning = VendorEarning::query()->where('order_id', $fitOrder->id)->sole();
    // Commission on the discounted goods (90), the shop wears its own code: 90 + 30 − 9 = 111.
    expect($earning->discount_funding)->toBe('vendor')->and((string) $earning->commission)->toBe('9.00')->and((string) $earning->net)->toBe('111.00');

    // The library, courses and anyone who passes no scope refuse it.
    expect(fn () => app(ResolveDiscountAction::class)->execute('FIT10', $customer->id, 100))->toThrow(ValidationException::class);

    // Paused by its owner, the code stops working.
    polishAs($owner)->post(route('vendor.discount-codes.status', $code->id), ['active' => 0])->assertSessionHasNoErrors();
    expect($code->refresh()->status)->toBe('inactive');
    $noorOwner = User::query()->find(VendorMember::query()->where('vendor_id', $noor->id)->where('role', 'owner')->value('user_id'));
    polishAs($noorOwner)->post(route('vendor.discount-codes.status', $code->id), ['active' => 1])->assertNotFound();
});

it('makes delivery free over the shop\'s amount, and nudges the cart towards it', function () {
    [$fitrah, $owner] = polishShop('fitrah');
    $fitrah->update(['free_delivery_over' => 150]);
    $book = polishProduct($fitrah, 'Threshold Book', 100);
    $customer = polishCustomer();
    $cart = Cart::query()->create(['user_id' => $customer->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => 1]);
    polishAs($customer)->get(route('public.shop.cart'))->assertOk()->assertSee('50.00');
    $cart->items()->delete();

    polishCheckout($customer, [[$book, 1]])->assertSessionHasNoErrors();
    expect((string) Order::query()->where('user_id', $customer->id)->latest('id')->value('delivery_fee'))->toBe('30.00');

    $second = polishCustomer('Mariyam Ali');
    polishCheckout($second, [[$book, 2]])->assertSessionHasNoErrors();
    expect((string) Order::query()->where('user_id', $second->id)->value('delivery_fee'))->toBe('0.00');

    polishAs($owner)->get(route('vendor.index'))->assertOk();
});

it('lets the office merchandise the shop home: a hero, featured products in its order, a collection', function () {
    [$fitrah] = polishShop();
    $one = polishProduct($fitrah, 'Featured One', 40);
    $two = polishProduct($fitrah, 'Featured Two', 40);
    $collection = VendorCollection::query()->create(['vendor_id' => $fitrah->id, 'name' => 'Back to school', 'slug' => 'back-to-school', 'is_active' => true]);
    $collection->products()->attach($one->id, ['sort_order' => 1]);
    $office = polishOffice();

    polishAs($office)->post(route('admin.bookshop.home.store'), ['kind' => 'hero', 'heading' => 'Ready for school', 'link' => ['kind' => 'vendor', 'target' => 'fitrah']])->assertSessionHasNoErrors();
    polishAs($office)->post(route('admin.bookshop.home.store'), ['kind' => 'product', 'product_id' => $one->id])->assertSessionHasNoErrors();
    polishAs($office)->post(route('admin.bookshop.home.store'), ['kind' => 'product', 'product_id' => $two->id])->assertSessionHasNoErrors();
    polishAs($office)->post(route('admin.bookshop.home.store'), ['kind' => 'collection', 'vendor_collection_id' => $collection->id])->assertSessionHasNoErrors();
    polishAs($office)->post(route('admin.bookshop.home.store'), ['kind' => 'product', 'product_id' => 999999])->assertSessionHasErrors();
    polishAs(polishCustomer())->post(route('admin.bookshop.home.store'), ['kind' => 'hero', 'heading' => 'x'])->assertForbidden();

    $home = app(PresentShopHomeAction::class)->execute();
    expect($home['hero'][0]['heading'])->toBe('Ready for school')
        ->and(array_column($home['featured'], 'title'))->toBe(['Featured One', 'Featured Two'])
        ->and($home['collections'][0]['name'])->toBe('Back to school');

    // Moved: two before one.
    $twoFeature = \App\Domains\Bookshop\Models\ShopHomeFeature::query()->where('product_id', $two->id)->value('id');
    polishAs($office)->post(route('admin.bookshop.home.move', $twoFeature), ['direction' => -1])->assertRedirect();
    expect(array_column(app(PresentShopHomeAction::class)->execute()['featured'], 'title'))->toBe(['Featured Two', 'Featured One']);

    // An archived product drops off the home without the office doing anything.
    $two->update(['status' => 'archived']);
    expect(array_column(app(PresentShopHomeAction::class)->execute()['featured'], 'title'))->toBe(['Featured One']);
    polishAs()->get(route('public.shop.index'))->assertOk()->assertSee('Ready for school')->assertSee('Featured One');

    polishAs($office)->delete(route('admin.bookshop.home.destroy', $twoFeature))->assertRedirect();
    expect(app(ManageShopHomeAction::class)->list()['features'] ?? null)->not->toBeNull();
    polishAs($office)->get(route('admin.bookshop.index'))->assertOk();
});
