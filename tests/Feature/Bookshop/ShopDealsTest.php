<?php

use App\Domains\Bookshop\Actions\CreateVendorAction;
use App\Domains\Bookshop\Actions\ResolveVendorScopeAction;
use App\Domains\Bookshop\Actions\Vendor\SaveVendorProductAction;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Bookshop\Support\SalePrice;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * STATUS §5lb, timed sales and the deals page: a shop puts a product on sale
 * at a percentage off until a set end. While it runs, the card, the product
 * page, the cart and the checkout all use the lower price; after it ends, the
 * list price again. The deals page lists what is on sale now, ending soonest
 * first. A discount code applies on top, to the sale price.
 */
function dealShop(string $slug = 'deal-shop'): Vendor
{
    return Vendor::query()->create(['name' => 'Deal Shop', 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active']);
}

function dealProduct(Vendor $vendor, string $title, float $price, array $overrides = []): Product
{
    return Product::query()->create($overrides + [
        'vendor_id' => $vendor->id, 'slug' => Str::slug($title), 'title' => $title, 'price' => $price,
        'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => 10, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

function dealVisitor()
{
    return test()->withoutLocalizationMiddleware();
}

it('knows when a sale runs: started, not ended, a sensible percentage', function () {
    $shop = dealShop();
    $running = dealProduct($shop, 'Running', 100, ['sale_percent' => 25, 'sale_ends_at' => now()->addDay()]);
    $later = dealProduct($shop, 'Later', 100, ['sale_percent' => 25, 'sale_starts_at' => now()->addHour(), 'sale_ends_at' => now()->addDay()]);
    $ended = dealProduct($shop, 'Ended', 100, ['sale_percent' => 25, 'sale_ends_at' => now()->subMinute()]);
    $endless = dealProduct($shop, 'Endless', 100, ['sale_percent' => 25]);

    expect(SalePrice::active($running))->toBeTrue()->and(SalePrice::apply(100.0, $running))->toBe(75.0)
        ->and(SalePrice::active($later))->toBeFalse()->and(SalePrice::apply(100.0, $later))->toBe(100.0)
        ->and(SalePrice::active($ended))->toBeFalse()
        ->and(SalePrice::active($endless))->toBeFalse();
    // It starts by itself when its hour comes.
    $this->travel(2)->hours();
    expect(SalePrice::active($later->refresh()))->toBeTrue();
});

it('shows the sale price with the list price struck through, a percentage badge and the end', function () {
    $shop = dealShop();
    $book = dealProduct($shop, 'Arabic Workbook', 120, ['sale_percent' => 20, 'sale_ends_at' => now()->addDays(2)]);
    ProductVariant::query()->create(['product_id' => $book->id, 'name' => 'Hardback', 'price' => 200, 'stock' => 5, 'is_active' => true]);

    dealVisitor()->get(route('public.shop.index', ['q' => 'Arabic']))->assertOk()
        ->assertSee('MVR 96.00')->assertSee('120.00')
        ->assertSee(__('shop.percent_off', ['percent' => 20]))
        ->assertSee('data-testid="card-sale-ends"', false);

    dealVisitor()->get(route('public.shop.product', $book->slug))->assertOk()
        ->assertSee('data-testid="product-sale"', false)
        ->assertSee('Hardback · MVR 160.00')
        ->assertSee('"priceValidUntil":"'.now()->addDays(2)->toDateString().'"', false);

    // Ended: the list price, no badge, no clock.
    $book->forceFill(['sale_ends_at' => now()->subMinute()])->save();
    dealVisitor()->get(route('public.shop.product', $book->slug))->assertOk()
        ->assertSee('MVR 120.00')->assertDontSee('data-testid="product-sale"', false)->assertSee('Hardback · MVR 200.00');
});

it('lists what is on sale now on the deals page, ending soonest first, and on the shop home', function () {
    $shop = dealShop();
    dealProduct($shop, 'Ends Late', 100, ['sale_percent' => 10, 'sale_ends_at' => now()->addDays(5)]);
    dealProduct($shop, 'Ends Soon', 100, ['sale_percent' => 30, 'sale_ends_at' => now()->addHours(3)]);
    dealProduct($shop, 'Not Yet', 100, ['sale_percent' => 30, 'sale_starts_at' => now()->addDay(), 'sale_ends_at' => now()->addDays(3)]);
    dealProduct($shop, 'Over', 100, ['sale_percent' => 30, 'sale_ends_at' => now()->subDay()]);
    dealProduct($shop, 'Full Price', 100);

    $page = dealVisitor()->get(route('public.shop.deals'))->assertOk()
        ->assertSee(__('shop.deals_heading'))->assertSeeInOrder(['Ends Soon', 'Ends Late'])
        ->assertDontSee('Not Yet')->assertDontSee('Over')->assertDontSee('Full Price');
    expect($page->getContent())->toContain(__('shop.result_count', ['count' => 2]))
        ->and($page->getContent())->toMatch('#<option value=""\s+selected[^>]*>'.preg_quote(__('shop.sort_ending_soon'), '#').'#');

    dealVisitor()->get(route('public.shop.index'))->assertOk()
        ->assertSee('data-testid="shop-deals"', false)->assertSee('data-testid="shop-deals-all"', false)
        ->assertSee('data-testid="shop-link-deals"', false);

    // No deals: the page says so, and the home has no deals shelf.
    Product::query()->update(['sale_percent' => null, 'sale_ends_at' => null]);
    dealVisitor()->get(route('public.shop.deals'))->assertOk()->assertSee(__('shop.no_deals_now'));
    dealVisitor()->get(route('public.shop.index'))->assertOk()->assertDontSee('data-testid="shop-deals"', false);
});

it('charges the sale price in the cart and at checkout, with a discount code on top — and the list price once it ends', function () {
    $shop = dealShop();
    $book = dealProduct($shop, 'Tracing Book', 100, ['sale_percent' => 25, 'sale_ends_at' => now()->addDay()]);
    $user = User::factory()->create();
    app(CreditWalletAction::class)->execute($user->id, 1000, 'admin', null, 'Top-up');
    app(SaveDiscountCodeAction::class)->execute(['code' => 'TEN', 'discount_type' => 'fixed', 'discount_value' => 10, 'per_user_limit' => 5]);
    $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => 2]);

    dealVisitor()->actingAs($user)->get(route('public.shop.cart'))->assertOk()
        ->assertSee('MVR 75.00')->assertSee('data-testid="cart-was-price"', false)->assertSee('MVR 150.00');

    dealVisitor()->actingAs($user)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'Aishath', 'phone' => '7700000', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => ['deal-shop' => 't0'], 'payment_method' => 'wallet', 'discount_code' => 'TEN',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $checkout = BookshopCheckout::query()->sole();
    expect((string) $checkout->subtotal)->toBe('150.00')->and((string) $checkout->discount)->toBe('10.00')
        ->and((string) OrderItem::query()->sole()->unit_price)->toBe('75.00')
        ->and((string) Order::query()->sole()->total)->toBe((string) $checkout->total);

    // After the sale: the list price.
    $this->travel(2)->days();
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => 1]);
    dealVisitor()->actingAs($user)->get(route('public.shop.cart'))->assertOk()
        ->assertSee('MVR 100.00')->assertDontSee('data-testid="cart-was-price"', false);
});

it('lets a shop set a sale from its product form, checks it, and ends it with an empty percentage', function () {
    Role::findOrCreate('vendor', 'web');
    $created = app(CreateVendorAction::class)->execute(['name' => 'Sale Shop', 'owner_name' => 'Owner', 'owner_email' => 'sale-owner@example.test'], User::factory()->create()->id);
    VendorMember::query()->where('vendor_id', $created['vendor_id'])->update(['agreement_accepted_at' => now()]);
    $owner = User::query()->findOrFail($created['owner_user_id']);
    $input = fn (array $o = []) => $o + ['title' => 'Sale Book', 'price' => '80.00', 'tax_class' => 'zero_rated', 'status' => 'active', 'visibility' => 'shop', 'stock' => 5, 'track_stock' => 1];
    $as = fn () => dealVisitor()->actingAs($owner);

    $as()->post(route('vendor.products.store'), $input(['sale_percent' => 95, 'sale_ends_at' => now()->addDay()->format('Y-m-d\TH:i')]))->assertSessionHasErrors('sale_percent');
    $as()->post(route('vendor.products.store'), $input(['sale_percent' => 20]))->assertSessionHasErrors('sale_ends_at');
    $as()->post(route('vendor.products.store'), $input(['sale_percent' => 20, 'sale_ends_at' => now()->subHour()->format('Y-m-d\TH:i')]))->assertSessionHasErrors('sale_ends_at');
    $as()->post(route('vendor.products.store'), $input(['sale_percent' => 20, 'sale_starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'sale_ends_at' => now()->addDay()->format('Y-m-d\TH:i')]))->assertSessionHasErrors('sale_ends_at');
    expect(Product::query()->count())->toBe(0);

    $as()->post(route('vendor.products.store'), $input(['sale_percent' => 20, 'sale_ends_at' => now()->addDay()->format('Y-m-d\TH:i')]))->assertSessionHasNoErrors();
    $product = Product::query()->sole();
    expect($product->sale_percent)->toBe(20)->and(SalePrice::active($product))->toBeTrue();
    $as()->get(route('vendor.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('products.0.sale_percent', 20)->where('products.0.sale_state', 'running'));

    // A save without the sale fields (a CSV row, a bulk change) leaves it running.
    app(SaveVendorProductAction::class)->execute(
        app(ResolveVendorScopeAction::class)->execute($owner->id),
        $input(['price' => '90.00']), $product->id,
    );
    expect($product->refresh()->sale_percent)->toBe(20);

    $as()->post(route('vendor.products.update', $product->id), $input(['sale_percent' => '', 'sale_ends_at' => '']))->assertSessionHasNoErrors();
    expect($product->refresh()->sale_percent)->toBeNull()->and($product->sale_ends_at)->toBeNull();
});
