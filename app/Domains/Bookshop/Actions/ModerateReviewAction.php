<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Models\ProductReview;
use App\Domains\Bookshop\Support\Merchandise;
use Illuminate\Validation\ValidationException;

/**
 * The office moderates reviews (BOOKSHOP_PLAN §4 "office may hide";
 * decision 12): hide one with a note the shop sees, publish one that was
 * hidden or held for pre-moderation. The product's stars follow at once.
 */
class ModerateReviewAction
{
    /**
     * The newest reviews across the shop, those waiting first.
     *
     * @return list<array<string, mixed>>
     */
    public function list(int $limit = 200): array
    {
        $reviews = ProductReview::query()->with(['product:id,title,slug,vendor_id', 'product.vendor:id,name', 'order:id,number'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [ProductReview::PENDING])->latest()->limit($limit)->get();

        return $reviews->map(fn (ProductReview $r) => [
            'id' => $r->id,
            'product' => $r->product?->title,
            'product_slug' => $r->product?->slug,
            'vendor' => $r->product?->vendor?->name,
            'order_number' => $r->order?->number,
            'rating' => (int) $r->rating,
            'body' => $r->body,
            'reply' => $r->vendor_reply,
            'status' => $r->status,
            'moderation_note' => $r->moderation_note,
            'created_at' => $r->created_at?->toDateTimeString(),
        ])->values()->all();
    }

    public function execute(int $reviewId, string $action, int $officeUserId, ?string $note = null): ProductReview
    {
        $review = ProductReview::query()->whereKey($reviewId)->firstOrFail();
        $note = trim((string) $note) !== '' ? mb_substr(trim((string) $note), 0, 500) : null;
        match ($action) {
            'hide' => $review->update(['status' => ProductReview::HIDDEN, 'moderated_at' => now(), 'moderated_by' => $officeUserId, 'moderation_note' => $note ?? throw ValidationException::withMessages(['note' => __('shop.error_moderation_note_required')])]),
            'publish' => $review->update(['status' => ProductReview::PUBLISHED, 'moderated_at' => now(), 'moderated_by' => $officeUserId, 'moderation_note' => $note]),
            default => throw ValidationException::withMessages(['action' => __('shop.error_moderation_action')]),
        };
        Merchandise::refreshRating((int) $review->product_id);

        return $review->refresh();
    }
}
