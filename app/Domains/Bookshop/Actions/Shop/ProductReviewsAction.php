<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductReview;
use App\Domains\Bookshop\Support\Merchandise;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reviews and ratings (BOOKSHOP_PLAN §4 "Trust": "reviews only from
 * customers who received the product; vendor may reply; office may hide";
 * decision 12: on, moderated). One review per delivered order line, by the
 * customer who received it; live at once unless the office turned on
 * pre-moderation; the product's average and count follow every change.
 * Reviewers are shown by first name and initial, marked as a verified
 * purchase — never their full name, island or phone.
 */
class ProductReviewsAction
{
    /**
     * The product page's reviews: the summary, the newest published ones,
     * and whether this customer may write one now.
     *
     * @return array<string, mixed>
     */
    public function forProduct(int $productId, ?int $userId): array
    {
        $published = ProductReview::query()->where('product_id', $productId)->where('status', ProductReview::PUBLISHED);
        $distribution = (clone $published)->selectRaw('rating, count(*) as n')->groupBy('rating')->pluck('n', 'rating');
        $count = (int) $distribution->sum();
        $reviews = (clone $published)->latest()->limit(20)->get();
        $names = $this->names($reviews->pluck('user_id')->all());

        return [
            'count' => $count,
            'avg' => $count > 0 ? number_format((float) $distribution->map(fn ($n, $r) => $n * $r)->sum() / $count, 1, '.', '') : null,
            'distribution' => collect([5, 4, 3, 2, 1])->mapWithKeys(fn (int $r) => [$r => (int) ($distribution[$r] ?? 0)])->all(),
            'reviews' => $reviews->map(fn (ProductReview $r) => [
                'id' => $r->id,
                'rating' => (int) $r->rating,
                'body' => $r->body,
                'name' => $names[$r->user_id] ?? __('shop.a_customer'),
                'date' => $r->created_at?->toDateString(),
                'reply' => $r->vendor_reply,
                'replied_at' => $r->replied_at?->toDateString(),
            ])->values()->all(),
            'can_review' => $userId !== null && $this->eligibleItem($userId, $productId) !== null,
            'pending_mine' => $userId !== null && ProductReview::query()->where('product_id', $productId)->where('user_id', $userId)->where('status', ProductReview::PENDING)->exists(),
        ];
    }

    /** A delivered line of this product, bought by this customer, not yet reviewed. */
    public function eligibleItem(int $userId, int $productId): ?OrderItem
    {
        return OrderItem::query()->where('product_id', $productId)
            ->whereHas('order', fn ($q) => $q->where('user_id', $userId)->where('status', OrderStatus::Delivered->value))
            ->whereNotExists(fn ($q) => $q->from('product_reviews')->whereColumn('product_reviews.order_item_id', 'order_items.id'))
            ->orderBy('id')->first();
    }

    public function submit(int $userId, string $productSlug, int $rating, ?string $body): ProductReview
    {
        $product = ListShopProductsAction::forSale()->where('slug', $productSlug)->firstOrFail();
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages(['rating' => __('shop.error_review_rating')]);
        }
        $body = trim((string) $body) !== '' ? mb_substr(trim((string) $body), 0, (int) config('bookshop.reviews.max_body', 2000)) : null;
        $premoderate = (bool) config('bookshop.reviews.premoderate');

        $review = DB::transaction(function () use ($userId, $product, $rating, $body, $premoderate) {
            $item = $this->eligibleItem($userId, (int) $product->id);
            if ($item === null) {
                throw ValidationException::withMessages(['rating' => __('shop.error_review_not_eligible')]);
            }

            return ProductReview::query()->create([
                'product_id' => $product->id,
                'vendor_id' => $product->vendor_id,
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'user_id' => $userId,
                'rating' => $rating,
                'body' => $body,
                'status' => $premoderate ? ProductReview::PENDING : ProductReview::PUBLISHED,
            ]);
        });
        Merchandise::refreshRating((int) $product->id);

        $notify = app(NotifyBookshopUserAction::class);
        $notify->vendor((int) $product->vendor_id, __('shop.notice_review_title'), __('shop.notice_review_body', ['title' => $product->title, 'rating' => $rating]), '/vendor/reviews');
        if ($premoderate) {
            $notify->office(__('shop.notice_review_pending_title'), __('shop.notice_review_body', ['title' => $product->title, 'rating' => $rating]), '/admin/bookshop');
        }

        return $review;
    }

    /**
     * "Aishath M." — first name and the initial of the last, through the
     * auth model from config so Bookshop never imports Identity's (rule 3).
     *
     * @param  list<int>  $userIds
     * @return array<int, string>
     */
    private function names(array $userIds): array
    {
        $userModel = config('auth.providers.users.model');

        return $userModel::query()->whereIn('id', array_unique($userIds))->pluck('name', 'id')
            ->map(function ($name) {
                $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
                $first = $parts[0] ?? '';
                $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1).'.' : '';

                return trim($first.' '.$last);
            })->all();
    }
}
