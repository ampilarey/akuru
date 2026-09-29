<?php

use App\Domains\Bookshop\Actions\Money\ReferralCreditAction;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Referral;
use App\Domains\Bookshop\Models\ReferralCode;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Models\Wallet;
use App\Domains\Commerce\Models\WalletTransaction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * STATUS §5ln, referral credit: off until the office turns it on; then a
 * customer's share link, a friend's first order through it, and — once
 * that order is delivered and past its return window — wallet credit for
 * both. Never oneself, one per friend, nothing for a short or cancelled
 * first order.
 */
function referralOffice(): User
{
    Role::findOrCreate('super_admin', 'web');
    Permission::findOrCreate('bookshop.manage', 'web');
    $office = User::factory()->create();
    $office->assignRole('super_admin');
    $office->givePermissionTo('bookshop.manage');

    return $office;
}

function referralsOn(array $numbers = []): void
{
    test()->withoutLocalizationMiddleware()->actingAs(referralOffice())
        ->post(route('admin.bookshop.referrals'), $numbers + ['on' => 1, 'referrer_amount' => 25, 'friend_amount' => 20, 'min_order' => 100])
        ->assertSessionHasNoErrors();
    auth()->logout();
    test()->travel(1)->minutes();
}

function referralBook(float $price = 150): Product
{
    $vendor = Vendor::query()->firstOrCreate(['slug' => 'ref-shop'], ['name' => 'Ref Shop', 'code' => 'REF', 'status' => 'active']);

    return Product::query()->firstOrCreate(['slug' => 'ref-book-'.(int) $price], [
        'vendor_id' => $vendor->id, 'title' => 'Ref Book '.(int) $price, 'price' => $price, 'currency' => 'MVR',
        'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop',
    ]);
}

/** A wallet checkout of one book, through the real checkout. */
function referralCheckout(User $buyer, float $price = 150): BookshopCheckout
{
    app(CreditWalletAction::class)->execute($buyer->id, 1000, 'admin', null, 'Top-up');
    $cart = Cart::query()->firstOrCreate(['user_id' => $buyer->id]);
    CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => referralBook($price)->id, 'quantity' => 1]);
    test()->withoutLocalizationMiddleware()->actingAs($buyer)->post(route('public.shop.checkout.store'), [
        'recipient_name' => 'A', 'phone' => '7700000', 'atoll' => 'K', 'island' => 'Malé', 'street' => 'M. Example',
        'delivery' => ['ref-shop' => 't0'], 'payment_method' => 'wallet',
    ])->assertSessionHasNoErrors();

    return BookshopCheckout::query()->where('user_id', $buyer->id)->latest('id')->firstOrFail();
}

function deliverCheckout(BookshopCheckout $checkout): void
{
    Order::query()->where('bookshop_checkout_id', $checkout->id)->update(['status' => 'delivered', 'delivered_at' => now()]);
}

function referralVisit(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

it('is off until the office turns it on: no link, nothing kept, nothing paid', function () {
    $referrer = User::factory()->create();
    $code = ReferralCode::query()->create(['user_id' => $referrer->id, 'code' => 'ABCDEFGH'])->code;
    $friend = User::factory()->create();

    referralVisit($referrer)->get(route('public.shop.orders'))->assertOk()->assertDontSee('data-testid="referral-invite"', false);
    referralVisit($friend)->get(route('public.shop.referral', $code))->assertRedirect(route('public.shop.index'))->assertSessionMissing(ReferralCreditAction::SESSION_KEY);
    referralCheckout($friend);
    expect(Referral::query()->count())->toBe(0);
    $this->artisan('bookshop:award-rewards')->expectsOutput('Referrals paid: 0');
});

it('credits both once a friend\'s first order is delivered and past its return window', function () {
    referralsOn();
    $referrer = User::factory()->create();
    $friend = User::factory()->create();

    $invite = referralVisit($referrer)->get(route('public.shop.orders'))->assertOk()
        ->assertSee('data-testid="referral-invite"', false)->assertSee('MVR 25.00')->assertSee('MVR 20.00');
    $code = ReferralCode::query()->where('user_id', $referrer->id)->value('code');
    $invite->assertSee(route('public.shop.referral', $code), false);

    auth()->logout();
    referralVisit($friend)->get(route('public.shop.referral', strtolower($code)))
        ->assertRedirect(route('public.shop.index'))->assertSessionHas(ReferralCreditAction::SESSION_KEY, $code)
        ->assertSessionHas('success', __('shop.referral_welcome_flash', ['amount' => 'MVR 20.00']));
    CartItem::query()->create(['cart_id' => Cart::query()->create(['user_id' => $friend->id])->id, 'product_id' => referralBook()->id, 'quantity' => 1]);
    referralVisit($friend)->get(route('public.shop.checkout'))->assertOk()->assertSee('data-testid="checkout-referral"', false);
    CartItem::query()->delete();

    $checkout = referralCheckout($friend);
    $referral = Referral::query()->sole();
    expect($referral)->status->toBe('pending')->referrer_user_id->toBe($referrer->id)->bookshop_checkout_id->toBe($checkout->id)
        ->and(session(ReferralCreditAction::SESSION_KEY))->toBeNull();

    $this->artisan('bookshop:award-rewards')->expectsOutput('Referrals paid: 0');
    deliverCheckout($checkout);
    $this->travel(3)->days();
    $this->artisan('bookshop:award-rewards')->expectsOutput('Referrals paid: 0');
    $this->travel(5)->days();
    $this->artisan('bookshop:award-rewards')->expectsOutput('Referrals paid: 1');
    $this->artisan('bookshop:award-rewards')->expectsOutput('Referrals paid: 0');

    expect($referral->refresh())->status->toBe('paid')->base_amount->toBe('150.00')->referrer_amount->toBe('25.00')->friend_amount->toBe('20.00')
        ->and((string) Wallet::query()->where('user_id', $referrer->id)->value('balance'))->toBe('25.00')
        ->and(WalletTransaction::query()->where('source_type', 'referral')->where('source_id', $referral->id)->count())->toBe(2)
        ->and($referral->friend_wallet_transaction_id)->toBe(WalletTransaction::query()->where('user_id', $friend->id)->where('source_type', 'referral')->value('id'));

    referralVisit($referrer)->get(route('public.shop.orders'))->assertSee(__('shop.referral_counts', ['paid' => 1, 'pending' => 0]));
});

it('never pays for oneself, a second referral, someone who ordered before, or a first order too small or cancelled', function () {
    referralsOn();
    $referrer = User::factory()->create();
    $code = app(ReferralCreditAction::class)->codeFor($referrer->id);

    // Oneself.
    referralVisit($referrer)->get(route('public.shop.referral', $code));
    referralCheckout($referrer);
    expect(Referral::query()->count())->toBe(0);

    // Someone who already had a paid order.
    $regular = User::factory()->create();
    referralCheckout($regular);
    referralVisit($regular)->get(route('public.shop.referral', $code));
    referralCheckout($regular);
    expect(Referral::query()->count())->toBe(0);

    // A first order under the office's MVR 100, and a cancelled one: kept, then void.
    $small = User::factory()->create();
    referralVisit($small)->get(route('public.shop.referral', $code));
    deliverCheckout(referralCheckout($small, 60));
    $gone = User::factory()->create();
    referralVisit($gone)->get(route('public.shop.referral', $code));
    $goneCheckout = referralCheckout($gone);
    Order::query()->where('bookshop_checkout_id', $goneCheckout->id)->update(['status' => 'cancelled', 'cancelled_at' => now()]);

    $this->travel(8)->days();
    $this->artisan('bookshop:award-rewards')->expectsOutput('Referrals paid: 0');
    expect(Referral::query()->pluck('status')->all())->toBe(['void', 'void'])
        ->and(WalletTransaction::query()->where('source_type', 'referral')->count())->toBe(0);

    // A friend already referred is not referred again, by anyone.
    $other = User::factory()->create();
    referralVisit($small)->get(route('public.shop.referral', app(ReferralCreditAction::class)->codeFor($other->id)));
    referralCheckout($small);
    expect(Referral::query()->where('referred_user_id', $small->id)->count())->toBe(1);
});

it('keeps a friend\'s referral on the checkout they actually paid, after an unpaid try', function () {
    referralsOn();
    $referrer = User::factory()->create();
    $friend = User::factory()->create();
    referralVisit($friend)->get(route('public.shop.referral', app(ReferralCreditAction::class)->codeFor($referrer->id)));

    $first = referralCheckout($friend);
    Order::query()->where('bookshop_checkout_id', $first->id)->update(['status' => 'expired', 'paid_at' => null]);
    $second = referralCheckout($friend);
    expect(Referral::query()->sole()->bookshop_checkout_id)->toBe($second->id);

    deliverCheckout($second);
    $this->travel(8)->days();
    $this->artisan('bookshop:award-rewards')->expectsOutput('Referrals paid: 1');
});

it('keeps the office\'s numbers in bounds, and its screens to the office', function () {
    $office = referralOffice();
    referralVisit($office)->get(route('admin.bookshop.index'))->assertInertia(fn ($page) => $page->where('referrals.settings.on', false));
    referralVisit($office)->post(route('admin.bookshop.referrals'), ['on' => 1, 'referrer_amount' => 0, 'friend_amount' => 900, 'min_order' => -1])
        ->assertSessionHasErrors(['referrer_amount', 'friend_amount', 'min_order']);
    referralVisit($office)->post(route('admin.bookshop.referrals'), ['on' => 1, 'referrer_amount' => 25, 'friend_amount' => 20, 'min_order' => 100])
        ->assertSessionHas('success', __('shop.referrals_on_flash'));
    referralVisit($office)->get(route('admin.bookshop.index'))->assertInertia(fn ($page) => $page->where('referrals.settings.on', true)->where('referrals.settings.friend_amount', 20));
    expect(referralVisit($office)->get(route('admin.bookshop.referrals.export'))->assertOk()->streamedContent())
        ->toContain('date,checkout,referrer_id,friend_id,status,goods_paid,referrer_credit,friend_credit,currency');

    auth()->logout();
    $stranger = User::factory()->create();
    referralVisit($stranger)->post(route('admin.bookshop.referrals'), ['on' => 0, 'referrer_amount' => 25, 'friend_amount' => 20, 'min_order' => 100])->assertForbidden();
    referralVisit($stranger)->get(route('admin.bookshop.referrals.export'))->assertForbidden();
});
