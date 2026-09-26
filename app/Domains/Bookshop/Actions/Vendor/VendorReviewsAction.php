<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\ProductReview;
use Illuminate\Validation\ValidationException;

/**
 * The shop's reviews (BOOKSHOP_PLAN §4 "vendor may reply"): every review
 * of its products — published, waiting for the office, or hidden, with the
 * office's note — and one public reply each, which it may edit. Owners
 * and staff alike: answering customers is order work. Only the scope's
 * vendor's reviews, ever.
 */
class VendorReviewsAction
{
    /**
     * @return array<string, mixed>
     */
    public function list(VendorScope $scope): array
    {
        $reviews = ProductReview::query()->where('vendor_id', $scope->vendorId)->with(['product:id,title,slug', 'order:id,number'])->latest()->limit(300)->get();
        $published = $reviews->where('status', ProductReview::PUBLISHED);

        return [
            'summary' => [
                'count' => $published->count(),
                'avg' => $published->count() > 0 ? number_format((float) $published->avg('rating'), 1, '.', '') : null,
                'unanswered' => $published->whereNull('vendor_reply')->count(),
            ],
            'reviews' => $reviews->map(fn (ProductReview $r) => [
                'id' => $r->id,
                'product' => $r->product?->title,
                'product_slug' => $r->product?->slug,
                'order_number' => $r->order?->number,
                'rating' => (int) $r->rating,
                'body' => $r->body,
                'status' => $r->status,
                'moderation_note' => $r->moderation_note,
                'reply' => $r->vendor_reply,
                'replied_at' => $r->replied_at?->toDateTimeString(),
                'created_at' => $r->created_at?->toDateTimeString(),
            ])->values()->all(),
        ];
    }

    public function reply(VendorScope $scope, int $reviewId, string $text): ProductReview
    {
        $review = ProductReview::query()->where('vendor_id', $scope->vendorId)->whereKey($reviewId)->firstOrFail();
        $text = trim($text);
        if ($text === '') {
            throw ValidationException::withMessages(['reply' => __('shop.error_reply_empty')]);
        }
        $first = $review->vendor_reply === null;
        $review->update(['vendor_reply' => mb_substr($text, 0, 2000), 'replied_at' => now(), 'replied_by' => $scope->userId]);
        if ($first) {
            app(NotifyBookshopUserAction::class)->execute((int) $review->user_id, __('shop.notice_review_reply_title'), __('shop.notice_review_reply_body', ['vendor' => $scope->vendorName]), '/shop/products/'.$review->product?->slug.'#reviews');
        }

        return $review;
    }
}
