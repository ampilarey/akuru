<?php

use App\Domains\Bookshop\Actions\Shop\RemindAbandonedCartsAction;
use App\Domains\Bookshop\Mail\BookshopNoticeMail;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Bookshop\Models\VendorNewsletterSubscriber;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\SaveNotificationPreferencesAction;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * BOOKSHOP_PLAN slice B9c: a shop's newsletter sign-up section (with
 * consent, a list and CSV for the shop, an unsubscribe page confirmed by a
 * button) and abandoned-cart reminders (once per cart, signed-in only,
 * never after an order, never to someone who switched shop notices off).
 */
function newsShop(string $slug = 'fitrah'): array
{
    Role::findOrCreate('vendor', 'web');
    $vendor = Vendor::query()->create(['name' => ucfirst($slug), 'slug' => $slug, 'code' => strtoupper(substr($slug, 0, 3)), 'status' => 'active']);
    $owner = User::factory()->create();
    VendorMember::query()->create(['vendor_id' => $vendor->id, 'user_id' => $owner->id, 'role' => 'owner', 'agreement_accepted_at' => now()]);

    return [$vendor, $owner];
}

function newsAs(?User $user = null)
{
    $t = test()->withoutLocalizationMiddleware();
    if ($user === null) {
        app('auth')->forgetGuards();

        return $t;
    }

    return $t->actingAs($user);
}

it('shows a newsletter section on the shop page and signs people up with their consent', function () {
    [$fitrah, $owner] = newsShop();
    [$noor, $noorOwner] = newsShop('noor');
    newsAs($owner)->post(route('vendor.storefront.sections.save'), ['sections' => [['type' => 'newsletter', 'settings' => ['heading' => 'Fitrah news'], 'visibility' => 'published']]])->assertSessionHasNoErrors();
    newsAs($owner)->post(route('vendor.storefront.publish'))->assertSessionHasNoErrors();

    newsAs()->get(route('public.shop.vendor', 'fitrah'))->assertOk()->assertSee('Fitrah news')->assertSee('data-testid="newsletter-signup"', false);

    newsAs()->post(route('public.shop.newsletter.subscribe', 'fitrah'), ['email' => 'Reader@Example.test', 'name' => 'Aisha'])->assertSessionHasErrors('consent');
    newsAs()->post(route('public.shop.newsletter.subscribe', 'fitrah'), ['email' => 'Reader@Example.test', 'name' => 'Aisha', 'consent' => 1])->assertRedirect()->assertSessionHas('newsletter_joined', 'fitrah');
    newsAs()->post(route('public.shop.newsletter.subscribe', 'fitrah'), ['email' => 'reader@example.test', 'consent' => 1]);
    $row = VendorNewsletterSubscriber::query()->sole();
    expect($row->email)->toBe('reader@example.test')->and($row->name)->toBe('Aisha')->and($row->vendor_id)->toBe($fitrah->id)->and(strlen($row->token))->toBe(48);

    newsAs($owner)->get(route('vendor.index'))->assertInertia(fn ($page) => $page->where('newsletter.active', 1)->where('newsletter.recent.0.email', 'reader@example.test'));
    $csv = newsAs($owner)->get(route('vendor.newsletter.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('reader@example.test')->toContain('/shop/newsletter/unsubscribe/'.$row->token);
    expect(newsAs($noorOwner)->get(route('vendor.newsletter.export'))->streamedContent())->not->toContain('reader@example.test');
});

it('unsubscribes only by the confirm button on the link, and a new sign-up renews the consent', function () {
    [$fitrah] = newsShop();
    newsAs()->post(route('public.shop.newsletter.subscribe', 'fitrah'), ['email' => 'reader@example.test', 'consent' => 1]);
    $row = VendorNewsletterSubscriber::query()->sole();

    newsAs()->get(route('public.shop.newsletter.unsubscribe', $row->token))->assertOk()->assertSee('reader@example.test')->assertSee(__('shop.newsletter_leave_button'));
    expect($row->refresh()->unsubscribed_at)->toBeNull();
    newsAs()->post(route('public.shop.newsletter.unsubscribe.confirm', $row->token))->assertRedirect(route('public.shop.newsletter.unsubscribe', $row->token));
    expect($row->refresh()->unsubscribed_at)->not->toBeNull();
    newsAs()->get(route('public.shop.newsletter.unsubscribe', $row->token))->assertSee(__('shop.newsletter_left', ['email' => 'reader@example.test', 'shop' => 'Fitrah']));
    newsAs()->get(route('public.shop.newsletter.unsubscribe', str_repeat('a', 48)))->assertNotFound();

    $before = $row->consented_at;
    $this->travel(2)->days();
    newsAs()->post(route('public.shop.newsletter.subscribe', 'fitrah'), ['email' => 'reader@example.test', 'consent' => 1]);
    expect($row->refresh()->unsubscribed_at)->toBeNull()->and($row->consented_at->gt($before))->toBeTrue();
});

it('reminds a signed-in customer once about a cart left for a day, and never after an order or opt-out', function () {
    Mail::fake();
    [$fitrah] = newsShop();
    $book = Product::query()->create(['vendor_id' => $fitrah->id, 'slug' => 'book', 'title' => 'Book', 'price' => 50, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop']);
    $cartFor = function (User $user, int $hoursAgo) use ($book): Cart {
        $cart = Cart::query()->create(['user_id' => $user->id]);
        $item = CartItem::query()->create(['cart_id' => $cart->id, 'product_id' => $book->id, 'quantity' => 2]);
        $item->forceFill(['created_at' => now()->subHours($hoursAgo), 'updated_at' => now()->subHours($hoursAgo)])->saveQuietly();

        return $cart;
    };
    $left = User::factory()->create();
    $fresh = User::factory()->create();
    $stale = User::factory()->create();
    $ordered = User::factory()->create();
    $quiet = User::factory()->create();
    $leftCart = $cartFor($left, 30);
    $cartFor($fresh, 3);
    $cartFor($stale, 24 * 10);
    $cartFor($ordered, 30);
    BookshopCheckout::query()->forceCreate(['number' => 'AK-2026-900001', 'user_id' => $ordered->id, 'status' => 'paid', 'payment_method' => 'wallet', 'address_snapshot' => [], 'subtotal' => 1, 'discount' => 0, 'delivery_total' => 0, 'total' => 1, 'currency' => 'MVR', 'created_at' => now()->subHours(2)]);
    $cartFor($quiet, 30);
    app(SaveNotificationPreferencesAction::class)->execute($quiet->id, ['shop' => false]);
    Cart::query()->create(['session_token' => 'guest-token'])->items()->create(['product_id' => $book->id, 'quantity' => 1]);

    expect(app(RemindAbandonedCartsAction::class)->execute())->toBe(2);
    $title = __('shop.notice_cart_reminder_title');
    expect(UserNotification::query()->where('title', $title)->pluck('user_id')->all())->toBe([$left->id]);
    Mail::assertQueued(BookshopNoticeMail::class, fn ($m) => $m->hasTo($left->email));
    expect($leftCart->refresh()->reminded_at)->not->toBeNull();

    // Once per cart…
    expect(app(RemindAbandonedCartsAction::class)->execute())->toBe(0);
    // …until they touch it again and leave it another day.
    $leftCart->items()->first()->forceFill(['updated_at' => now()->addHour()])->saveQuietly();
    $this->travel(26)->hours();
    expect(app(RemindAbandonedCartsAction::class)->execute())->toBeGreaterThanOrEqual(1);
    expect(UserNotification::query()->where('title', $title)->where('user_id', $left->id)->count())->toBe(2);

    $this->artisan('bookshop:remind-abandoned-carts')->assertSuccessful();
});
