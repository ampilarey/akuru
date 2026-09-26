<?php

use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN slice B2, the cart: a guest fills one, it follows them
 * into their account when they sign in, and every change re-checks what
 * can be sold now.
 */
function cartVendor(string $slug = 'fitrah'): Vendor
{
    return Vendor::query()->create(['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active']);
}

function cartProduct(Vendor $vendor, string $title, array $overrides = []): Product
{
    return Product::query()->create($overrides + [
        'vendor_id' => $vendor->id, 'slug' => \Illuminate\Support\Str::slug($title), 'title' => $title, 'price' => 100,
        'currency' => 'MVR', 'tax_class' => 'standard', 'track_stock' => true, 'stock' => 10, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

function shopAs(?User $user = null)
{
    $test = test()->withoutLocalizationMiddleware();

    return $user === null ? $test : $test->actingAs($user);
}

it('lets a guest add to a cart that is theirs by session, and shows it grouped by shop with a sign-in prompt', function () {
    $fitrah = cartVendor('fitrah');
    $other = cartVendor('other-shop');
    $book = cartProduct($fitrah, 'Arabic Workbook', ['price' => 85]);
    $puzzle = cartProduct($other, 'Wooden Puzzle', ['price' => 240]);

    shopAs()->post(route('public.shop.cart.add'), ['product' => $book->slug, 'quantity' => 2])
        ->assertRedirect(route('public.shop.cart'))
        ->assertSessionHas('success');
    shopAs()->post(route('public.shop.cart.add'), ['product' => $puzzle->slug]);
    shopAs()->post(route('public.shop.cart.add'), ['product' => $book->slug]);

    $cart = Cart::query()->firstOrFail();
    expect($cart->user_id)->toBeNull()
        ->and($cart->session_token)->not->toBeNull()
        ->and($cart->items()->count())->toBe(2)
        ->and($cart->items()->where('product_id', $book->id)->value('quantity'))->toBe(3);

    $page = shopAs()->get(route('public.shop.cart'))->assertOk();
    $page->assertSee('Fitrah')->assertSee('Other-shop')->assertSee('MVR 495.00')->assertSee('Sign in to check out');
    $page->assertSee(route('login'))->assertSee(route('otp.login.form'));
    $page->assertDontSee('Check out</a>', false);

    // Another browser sees nothing of it.
    test()->flushSession();
    shopAs()->get(route('public.shop.cart'))->assertOk()->assertSee('Your cart is empty.');
});

it('merges a guest cart into the account on sign-in, adding quantities', function () {
    $fitrah = cartVendor();
    $book = cartProduct($fitrah, 'Arabic Workbook');
    $mat = cartProduct($fitrah, 'Prayer Mat');
    $user = User::factory()->create();
    $own = Cart::query()->create(['user_id' => $user->id]);
    CartItem::query()->create(['cart_id' => $own->id, 'product_id' => $book->id, 'quantity' => 1]);

    shopAs()->post(route('public.shop.cart.add'), ['product' => $book->slug, 'quantity' => 2]);
    shopAs()->post(route('public.shop.cart.add'), ['product' => $mat->slug]);
    expect(Cart::query()->count())->toBe(2);

    shopAs($user)->get(route('public.shop.cart'))->assertOk()->assertSee('Check out');

    expect(Cart::query()->count())->toBe(1);
    $cart = Cart::query()->where('user_id', $user->id)->firstOrFail();
    expect($cart->items()->where('product_id', $book->id)->value('quantity'))->toBe(3)
        ->and($cart->items()->where('product_id', $mat->id)->value('quantity'))->toBe(1);
});

it('refuses what is not for sale, needs an option where there are options, and caps quantity at the stock', function () {
    $fitrah = cartVendor();
    $draft = cartProduct($fitrah, 'Draft Thing', ['status' => 'draft']);
    $few = cartProduct($fitrah, 'Few Left', ['stock' => 2]);
    $sized = cartProduct($fitrah, 'Sized Shirt', ['track_stock' => true, 'stock' => 0]);
    $small = ProductVariant::query()->create(['product_id' => $sized->id, 'name' => 'Small', 'stock' => 5, 'is_active' => true]);
    ProductVariant::query()->create(['product_id' => $sized->id, 'name' => 'Large', 'stock' => 0, 'is_active' => true]);
    $user = User::factory()->create();

    shopAs($user)->post(route('public.shop.cart.add'), ['product' => $draft->slug])->assertSessionHasErrors('product');
    shopAs($user)->post(route('public.shop.cart.add'), ['product' => $few->slug, 'quantity' => 3])->assertSessionHasErrors('quantity');
    shopAs($user)->post(route('public.shop.cart.add'), ['product' => $sized->slug])->assertSessionHasErrors('variant');
    shopAs($user)->post(route('public.shop.cart.add'), ['product' => $sized->slug, 'variant_id' => $small->id, 'quantity' => 2])->assertSessionHasNoErrors();
    shopAs($user)->post(route('public.shop.cart.add'), ['product' => $few->slug, 'quantity' => 2])->assertSessionHasNoErrors();

    $cart = Cart::query()->where('user_id', $user->id)->firstOrFail();
    $line = $cart->items()->where('product_id', $few->id)->firstOrFail();

    // Changing quantities re-checks stock; zero removes the line.
    shopAs($user)->post(route('public.shop.cart.update', $line->id), ['quantity' => 5])->assertSessionHasErrors('quantity');
    shopAs($user)->post(route('public.shop.cart.update', $line->id), ['quantity' => 1])->assertSessionHasNoErrors();
    expect($line->refresh()->quantity)->toBe(1);
    shopAs($user)->post(route('public.shop.cart.update', $line->id), ['quantity' => 0])->assertSessionHasNoErrors();
    expect(CartItem::query()->find($line->id))->toBeNull();

    // A line in somebody else's cart is not reachable.
    $other = User::factory()->create();
    $variantLine = $cart->items()->firstOrFail();
    shopAs($other)->post(route('public.shop.cart.update', $variantLine->id), ['quantity' => 1])->assertNotFound();

    // A product that went off sale is flagged on the cart page, not dropped.
    $sized->update(['status' => 'archived']);
    shopAs($user)->get(route('public.shop.cart'))->assertOk()->assertSee('Sized Shirt is no longer for sale.');
});

it('offers add-to-cart on the product page and the cart on the phone bar with a count', function () {
    $fitrah = cartVendor();
    $book = cartProduct($fitrah, 'Arabic Workbook');

    $page = shopAs()->get(route('public.shop.product', $book->slug))->assertOk();
    $page->assertSee('data-testid="add-to-cart"', false)->assertDontSee('Online ordering opens soon');

    shopAs()->post(route('public.shop.cart.add'), ['product' => $book->slug, 'quantity' => 2]);
    shopAs()->get(route('public.shop.product', $book->slug))->assertOk()->assertSee('data-testid="bar-cart-count">2<', false);

    $book->update(['stock' => 0]);
    shopAs()->get(route('public.shop.product', $book->slug))->assertOk()->assertDontSee('data-testid="add-to-cart"', false);
});
