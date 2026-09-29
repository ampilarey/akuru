<?php

use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductReview;
use App\Domains\Bookshop\Models\ReviewVote;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * STATUS §5lg, helpful review votes: a signed-in customer marks someone
 * else's published review helpful, once, and can take it back; the product
 * page lists the most helpful first.
 */
function voteReview(Product $product, User $author, int $rating, string $body, array $overrides = []): ProductReview
{
    $n = Str::random(6);
    $checkout = BookshopCheckout::query()->create(['number' => 'AK-'.$n, 'user_id' => $author->id, 'status' => 'paid', 'payment_method' => 'wallet', 'address_snapshot' => ['name' => 'A'], 'subtotal' => 0, 'discount' => 0, 'delivery_total' => 0, 'total' => 0, 'currency' => 'MVR', 'paid_at' => now()]);
    $order = Order::query()->create(['number' => 'AK-'.$n.'-V', 'bookshop_checkout_id' => $checkout->id, 'vendor_id' => $product->vendor_id, 'user_id' => $author->id, 'status' => 'delivered', 'delivery_kind' => 'collect_vendor', 'delivery_name' => 'Collect', 'address_snapshot' => ['name' => 'A'], 'subtotal' => 0, 'total' => 0, 'currency' => 'MVR', 'paid_at' => now()]);
    $item = OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $product->id, 'title' => $product->title, 'unit_price' => 1, 'quantity' => 1, 'line_total' => 1, 'tax_class' => 'zero_rated', 'tax_amount' => 0]);

    return ProductReview::query()->create($overrides + ['product_id' => $product->id, 'vendor_id' => $product->vendor_id, 'order_id' => $order->id, 'order_item_id' => $item->id, 'user_id' => $author->id, 'rating' => $rating, 'body' => $body, 'status' => 'published']);
}

function voteSetup(): array
{
    $vendor = Vendor::query()->create(['name' => 'Vote Shop', 'slug' => 'vote-shop', 'code' => 'VOT', 'status' => 'active']);
    $product = Product::query()->create(['vendor_id' => $vendor->id, 'slug' => 'vote-book', 'title' => 'Vote Book', 'price' => 50, 'currency' => 'MVR', 'tax_class' => 'zero_rated', 'track_stock' => false, 'stock' => 0, 'status' => 'active', 'visibility' => 'shop']);

    return [$vendor, $product];
}

function voteAs(?User $user = null)
{
    $t = test()->withoutLocalizationMiddleware();

    return $user ? $t->actingAs($user) : $t;
}

it('marks a review helpful once, takes it back, and lists the most helpful first', function () {
    [, $product] = voteSetup();
    $older = voteReview($product, User::factory()->create(), 5, 'Older but useful review');
    $this->travel(1)->hours();
    $newer = voteReview($product, User::factory()->create(), 4, 'Newer review');
    $reader = User::factory()->create();

    voteAs()->get(route('public.shop.product', $product->slug))->assertSeeInOrder(['Newer review', 'Older but useful review']);

    voteAs($reader)->post(route('public.shop.review.helpful', $older->id))
        ->assertRedirect(route('public.shop.product', $product->slug).'#review-'.$older->id)
        ->assertSessionHas('success', __('shop.review_helpful_flash'));
    expect($older->refresh()->helpful_count)->toBe(1)->and(ReviewVote::query()->count())->toBe(1);

    voteAs($reader)->get(route('public.shop.product', $product->slug))
        ->assertSeeInOrder(['Older but useful review', 'Newer review'])
        ->assertSee('aria-pressed="true" data-testid="helpful-'.$older->id.'"', false);
    auth()->logout();
    voteAs()->get(route('public.shop.product', $product->slug))->assertSee(trans_choice('shop.found_helpful', 1, ['count' => 1]));

    voteAs($reader)->post(route('public.shop.review.helpful', $older->id))->assertSessionHas('success', __('shop.review_unhelpful_flash'));
    expect($older->refresh()->helpful_count)->toBe(0)->and(ReviewVote::query()->count())->toBe(0);
});

it('refuses a vote on one\'s own review, on a hidden review, and from a guest', function () {
    [, $product] = voteSetup();
    $author = User::factory()->create();
    $own = voteReview($product, $author, 5, 'My own review');
    $hidden = voteReview($product, User::factory()->create(), 1, 'Hidden review', ['status' => 'hidden']);

    voteAs($author)->post(route('public.shop.review.helpful', $own->id))->assertSessionHasErrors('review');
    voteAs($author)->get(route('public.shop.product', $product->slug))->assertDontSee('data-testid="helpful-'.$own->id.'"', false);
    voteAs($author)->post(route('public.shop.review.helpful', $hidden->id))->assertNotFound();
    auth()->logout();
    voteAs()->post(route('public.shop.review.helpful', $own->id))->assertRedirect(route('login'));
    expect(ReviewVote::query()->count())->toBe(0);
});
