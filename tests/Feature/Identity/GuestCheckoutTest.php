<?php

use App\Domains\Bookshop\Actions\Cart\ResolveCartAction;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Library\Actions\PublishLibraryItemAction;
use App\Domains\Library\Actions\SaveLibraryItemAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

/**
 * STATUS §5ly, the owner (2026-09-30): "customer should be able to buy in
 * book store and digital library without sign in … make same way" as Bake &
 * Grill. A name and a mobile number make an account and sign it in; the buyer
 * goes on to the checkout they were at. A number that already signs in to an
 * account is sent to sign in instead.
 */
function guestShopBasket(): string
{
    $vendor = Vendor::query()->create(['name' => 'Fitrah', 'slug' => 'fitrah', 'code' => 'FIT', 'status' => 'active']);
    $product = Product::query()->create([
        'vendor_id' => $vendor->id, 'slug' => 'seerah', 'title' => 'Seerah', 'price' => 100, 'currency' => 'MVR',
        'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop',
    ]);
    $token = 'guest-token-'.uniqid();
    $cart = Cart::query()->create(['session_token' => $token]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2]);

    return $token;
}

function guestPaidItem()
{
    $item = app(SaveLibraryItemAction::class)->execute([
        'title' => 'Guest Tafsir Notes', 'content_type' => 'book', 'access_type' => 'paid',
        'body' => '<p>One.</p><!-- pagebreak --><p>Two.</p>',
    ]);
    $item->price = 120;
    $item->save();
    app(PublishLibraryItemAction::class)->execute($item->id, User::factory()->create()->id);

    return $item->refresh();
}

function guestPost(array $data, array $session = [])
{
    return test()->withoutLocalizationMiddleware()->withSession($session)->post(route('guest-checkout'), $data);
}

it('offers the guest form on the cart, and a name and number go straight to checkout with the basket', function () {
    $token = guestShopBasket();

    test()->withoutLocalizationMiddleware()->withSession([ResolveCartAction::SESSION_KEY => $token])
        ->get(route('public.shop.cart'))->assertOk()
        ->assertSee('data-testid="guest-checkout"', false)->assertSee('name="for" value="shop"', false)
        ->assertSee(__('account.guest_title'))->assertDontSee('data-testid="go-to-checkout"', false);

    guestPost(['guest_name' => 'Aishath Guest', 'guest_phone' => '7771234', 'for' => 'shop'], [ResolveCartAction::SESSION_KEY => $token])
        ->assertRedirect(route('public.shop.checkout'));

    $user = User::query()->where('name', 'Aishath Guest')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->phone)->toBe('+9607771234')->and($user->email)->toBeNull()->and($user->force_password_change)->toBeTrue()
        // The number is kept, not made a sign-in: nobody proved they own it.
        ->and(UserContact::query()->where('user_id', $user->id)->exists())->toBeFalse();

    // The checkout opens with the basket, and the name and number already filled in.
    $html = test()->withoutLocalizationMiddleware()->get(route('public.shop.checkout'))->assertOk()->getContent();
    expect($html)->toContain('Seerah')->toContain('value="Aishath Guest"')->toContain('value="+9607771234"');
    expect(Cart::query()->where('user_id', $user->id)->firstOrFail()->items()->sum('quantity'))->toEqual(2);
});

it('sends a number that already signs in to an account to sign in, and makes nothing', function () {
    $owner = User::factory()->create();
    UserContact::query()->create(['user_id' => $owner->id, 'type' => 'mobile', 'value' => '+9607775555', 'is_primary' => true, 'verified_at' => now()]);
    $before = User::query()->count();

    guestPost(['guest_name' => 'Someone', 'guest_phone' => '777 5555', 'for' => 'shop'])
        ->assertSessionHasErrors(['guest_phone' => __('account.guest_has_account')]);

    $this->assertGuest();
    expect(User::query()->count())->toBe($before);
});

it('does not treat an unproved number on another account as a sign-in', function () {
    // A number someone typed before, never verified: it signs nobody in, so it blocks nobody.
    $earlier = User::factory()->create(['phone' => '+9607776666']);
    UserContact::query()->create(['user_id' => $earlier->id, 'type' => 'mobile', 'value' => '+9607776666', 'is_primary' => true, 'verified_at' => null]);

    guestPost(['guest_name' => 'New buyer', 'guest_phone' => '7776666', 'for' => 'gift_card'])
        ->assertRedirect(route('public.gift-cards.index'));

    $this->assertAuthenticated();
    expect(auth()->id())->not->toBe($earlier->id);
});

it('takes a Library buyer back to the item, now able to buy it', function () {
    $item = guestPaidItem();

    test()->withoutLocalizationMiddleware()->get(route('public.library.show', $item->slug))->assertOk()
        ->assertSee('data-testid="guest-checkout"', false)->assertSee('name="slug" value="'.$item->slug.'"', false);

    guestPost(['guest_name' => 'Reader', 'guest_phone' => '9601234', 'for' => 'library', 'slug' => $item->slug])
        ->assertRedirect(route('public.library.show', $item->slug));

    $this->assertAuthenticated();
    test()->withoutLocalizationMiddleware()->get(route('public.library.show', $item->slug))->assertOk()
        ->assertDontSee('data-testid="guest-checkout"', false);
});

it('sends the buyer only to the three checkouts, never to a URL from the form', function () {
    guestPost(['guest_name' => 'X', 'guest_phone' => '7770001', 'for' => 'https://evil.test'])->assertSessionHasErrors('for');
    guestPost(['guest_name' => 'X', 'guest_phone' => '7770001', 'for' => 'library', 'slug' => '//evil.test'])->assertSessionHasErrors('slug');
    $this->assertGuest();
});

it('refuses a number that is not one, and limits tries per number', function () {
    guestPost(['guest_name' => 'X', 'guest_phone' => 'call me', 'for' => 'shop'])->assertSessionHasErrors('guest_phone');
    guestPost(['guest_name' => 'X', 'guest_phone' => '12', 'for' => 'shop'])->assertSessionHasErrors('guest_phone');
    $this->assertGuest();

    $owner = User::factory()->create();
    UserContact::query()->create(['user_id' => $owner->id, 'type' => 'mobile', 'value' => '+9607778888', 'is_primary' => true, 'verified_at' => now()]);
    RateLimiter::clear('guest-checkout:7778888:127.0.0.1');
    foreach (range(1, 10) as $i) {
        guestPost(['guest_name' => 'X', 'guest_phone' => '7778888', 'for' => 'shop'])->assertSessionHasErrors(['guest_phone' => __('account.guest_has_account')]);
    }
    guestPost(['guest_name' => 'X', 'guest_phone' => '7778888', 'for' => 'shop'])->assertSessionHasErrors(['guest_phone' => __('account.guest_too_many')]);
});

it('speaks Dhivehi and Arabic on the form', function () {
    guestShopBasket();
    app()->setLocale('dv');
    foreach (['dv', 'ar'] as $locale) {
        expect(__('account.guest_title', [], $locale))->not->toBe('account.guest_title')
            ->and(__('account.guest_has_account', [], $locale))->not->toBe(__('account.guest_has_account', [], 'en'));
    }
});
