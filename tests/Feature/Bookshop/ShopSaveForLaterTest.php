<?php

use App\Domains\Bookshop\Actions\Cart\ResolveCartAction;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * STATUS §5lf, save for later: a cart line set aside stays with the basket
 * but out of it — not counted, not charged, not checked out — and goes back
 * in re-checked like any add. A guest's saved lines come along when they
 * sign in.
 */
function laterShop(): Vendor
{
    return Vendor::query()->create(['name' => 'Later Shop', 'slug' => 'later-shop', 'code' => 'LAT', 'status' => 'active']);
}

function laterProduct(Vendor $vendor, string $title, float $price, array $overrides = []): Product
{
    return Product::query()->create($overrides + [
        'vendor_id' => $vendor->id, 'slug' => Str::slug($title), 'title' => $title, 'price' => $price,
        'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => true, 'stock' => 10, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

function laterAs(?User $user = null)
{
    $t = test()->withoutLocalizationMiddleware();

    return $user ? $t->actingAs($user) : $t;
}

it('sets a line aside: out of the count, the total and the checkout, and back in on request', function () {
    $shop = laterShop();
    $book = laterProduct($shop, 'Maths Workbook', 50);
    $atlas = laterProduct($shop, 'School Atlas', 120);
    $user = User::factory()->create();
    app(CreditWalletAction::class)->execute($user->id, 1000, 'admin', null, 'Top-up');
    $cart = Cart::query()->create(['user_id' => $user->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => 2]);
    $atlasLine = CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $atlas->id, 'quantity' => 1]);

    laterAs($user)->post(route('public.shop.cart.save', $atlasLine->id))->assertSessionHasNoErrors();
    expect($atlasLine->refresh()->saved_at)->not->toBeNull()
        ->and(app(ResolveCartAction::class)->count($user->id, null))->toBe(2);

    laterAs($user)->get(route('public.shop.cart'))->assertOk()
        ->assertSee('data-testid="cart-saved"', false)->assertSee(__('shop.saved_for_later_heading', ['count' => 1]))
        ->assertSee('MVR 100.00')->assertSeeInOrder([__('shop.saved_for_later_heading', ['count' => 1]), 'School Atlas']);

    // Checkout charges the basket only, and keeps the saved line.
    laterAs($user)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'A', 'phone' => '7700000', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => ['later-shop' => 't0'], 'payment_method' => 'wallet',
    ])->assertSessionHasNoErrors();
    expect((string) BookshopCheckout::query()->sole()->subtotal)->toBe('100.00')
        ->and(CartItem::query()->pluck('product_id')->all())->toBe([$atlas->id]);

    laterAs($user)->post(route('public.shop.cart.move', $atlasLine->id))->assertSessionHasNoErrors();
    expect($atlasLine->refresh()->saved_at)->toBeNull()->and(app(ResolveCartAction::class)->count($user->id, null))->toBe(1);
});

it('joins a saved line to the same thing already in the basket, and adding more never touches the saved one', function () {
    $shop = laterShop();
    $book = laterProduct($shop, 'Maths Workbook', 50);
    $user = User::factory()->create();

    laterAs($user)->post(route('public.shop.cart.add'), ['product' => $book->slug, 'quantity' => 2]);
    $first = CartItem::query()->sole();
    laterAs($user)->post(route('public.shop.cart.save', $first->id));
    laterAs($user)->post(route('public.shop.cart.add'), ['product' => $book->slug, 'quantity' => 1]);
    expect(CartItem::query()->count())->toBe(2)->and($first->refresh()->quantity)->toBe(2);

    laterAs($user)->post(route('public.shop.cart.move', $first->id))->assertSessionHasNoErrors();
    expect(CartItem::query()->sole()->quantity)->toBe(3);
});

it('keeps a line saved when it cannot go back in, and says why', function () {
    $shop = laterShop();
    $book = laterProduct($shop, 'Maths Workbook', 50, ['stock' => 1]);
    $user = User::factory()->create();
    $cart = Cart::query()->create(['user_id' => $user->id]);
    $line = CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => 1, 'saved_at' => now()]);

    $book->update(['stock' => 0]);
    laterAs($user)->get(route('public.shop.cart'))->assertSee(__('shop.saved_unavailable'));
    laterAs($user)->post(route('public.shop.cart.move', $line->id))->assertSessionHasErrors('quantity');
    expect($line->refresh()->saved_at)->not->toBeNull();

    $book->update(['stock' => 5, 'status' => 'archived']);
    laterAs($user)->post(route('public.shop.cart.move', $line->id))->assertSessionHasErrors('product');
    expect(CartItem::query()->whereKey($line->id)->exists())->toBeTrue();
});

it('brings a guest\'s saved lines along when they sign in, and never touches another basket', function () {
    $shop = laterShop();
    $book = laterProduct($shop, 'Maths Workbook', 50);
    $atlas = laterProduct($shop, 'School Atlas', 120);
    $guest = Cart::query()->create(['session_token' => 'guest-token']);
    CartItem::query()->create(['cart_id' => $guest->id, 'product_id' => $book->id, 'quantity' => 1]);
    CartItem::query()->create(['cart_id' => $guest->id, 'product_id' => $atlas->id, 'quantity' => 1, 'saved_at' => now()]);
    $user = User::factory()->create();
    $own = Cart::query()->create(['user_id' => $user->id]);
    CartItem::query()->create(['cart_id' => $own->id, 'product_id' => $atlas->id, 'quantity' => 2]);

    $merged = app(ResolveCartAction::class)->execute($user->id, 'guest-token');
    expect($merged->id)->toBe($own->id)
        ->and($merged->items()->pluck('quantity', 'product_id')->all())->toBe([$atlas->id => 2, $book->id => 1])
        ->and($merged->savedItems()->pluck('product_id')->all())->toBe([$atlas->id]);

    $stranger = User::factory()->create();
    $line = $merged->savedItems()->first();
    laterAs($stranger)->post(route('public.shop.cart.move', $line->id))->assertNotFound();
    laterAs($stranger)->post(route('public.shop.cart.save', $merged->items()->first()->id))->assertNotFound();
});
