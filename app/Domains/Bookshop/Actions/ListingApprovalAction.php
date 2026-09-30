<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Actions\Shop\CustomerListsAction;
use App\Domains\Bookshop\Actions\Shop\ResolveStorefrontAction;
use App\Domains\Bookshop\Enums\ProductStatus;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Support\ShopPresenter;
use App\Domains\Media\Actions\ResolvePublicImageVariantAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * COMMERCE_PARITY_PLAN P4: the office approves every listing (the owner:
 * "the admin approves every listing"). A shop's *Put on sale* becomes a
 * request (`pending_review`); the office approves it (`active`) or declines
 * it with a note (back to `draft`, the note on the shop's list).
 *
 * Decision D4 — which edits to a live product go back for approval: what
 * the product *is* (title, summary, description, category, new photos,
 * variant names) does; price, sale, stock, SKU and delivery do not. A shop
 * the office marks *trusted* skips the queue. Products on sale before this
 * slice stay on sale.
 */
class ListingApprovalAction
{
    /** The product's own fields that D4 sends back for approval. */
    public const WATCHED = [
        'title', 'title_dv', 'title_ar',
        'summary', 'summary_dv', 'summary_ar',
        'description', 'description_dv', 'description_ar',
        'product_category_id',
    ];

    public function trusted(int $vendorId): bool
    {
        return (bool) Vendor::query()->whereKey($vendorId)->value('trusted');
    }

    /**
     * Called by the product form after the new values are filled in and
     * before the save: turns a request to sell into `pending_review` where the
     * office has to look. Returns true when the product has just joined the
     * queue, so the office is told once.
     */
    public function gate(Product $product, ?string $was, int $vendorId, bool $variantsRenamed, bool $newPhotos): bool
    {
        if ($product->status !== ProductStatus::Active || $this->trusted($vendorId)) {
            return false;
        }
        $changes = $was === null ? [] : $this->changes($product);
        if ($variantsRenamed) {
            $changes['variants'] = ['from' => null, 'to' => null];
        }
        if ($newPhotos) {
            $changes['images'] = ['from' => null, 'to' => null];
        }

        if ($was === ProductStatus::PendingReview->value) {
            // Still waiting; a further edit joins the changes already listed.
            $product->status = ProductStatus::PendingReview;
            if ($changes !== [] && is_array($product->getOriginal('review_changes'))) {
                $product->review_changes = array_merge($product->getOriginal('review_changes'), $changes);
            }

            return false;
        }
        if ($was === ProductStatus::Active->value && $changes === []) {
            return false;
        }

        $product->status = ProductStatus::PendingReview;
        $product->submitted_at = now();
        $product->review_note = null;
        $product->review_changes = $was === ProductStatus::Active->value ? $changes : null;

        return true;
    }

    /**
     * The bulk *Put on sale* (B8): the shop's drafts and archived products
     * join the queue; products already on sale are left alone.
     *
     * @param  list<int>  $ids
     */
    public function bulkSubmit(int $vendorId, array $ids, int $userId): int
    {
        $count = Product::query()->where('vendor_id', $vendorId)->whereIn('id', $ids)
            ->whereIn('status', [ProductStatus::Draft->value, ProductStatus::Archived->value])
            ->update(['status' => ProductStatus::PendingReview->value, 'submitted_at' => now(), 'review_note' => null, 'review_changes' => null, 'updated_by' => $userId, 'updated_at' => now()]);
        if ($count > 0) {
            $this->announce($vendorId, $count);
        }

        return $count;
    }

    /** Tell the office a shop is waiting (in-app, to `bookshop.manage`). */
    public function announce(int $vendorId, int $count = 1, ?string $title = null): void
    {
        $shop = (string) Vendor::query()->whereKey($vendorId)->value('name');
        app(NotifyBookshopUserAction::class)->office(
            __('shop.notice_listing_submitted_title'),
            $title !== null
                ? __('shop.notice_listing_submitted_body', ['vendor' => $shop, 'title' => $title])
                : __('shop.notice_listings_submitted_body', ['vendor' => $shop, 'count' => $count]),
            '/admin/bookshop#listings'
        );
    }

    /**
     * The office's queue, oldest first; with $decided, the recent decisions too (the CSV).
     *
     * @return list<array<string, mixed>>
     */
    public function queue(int $limit = 200, bool $decided = false): array
    {
        $query = Product::query()->with(['vendor:id,name,slug', 'category:id,name', 'images' => fn ($q) => $q->orderBy('sort_order')]);
        $decided
            ? $query->where(fn ($q) => $q->where('status', ProductStatus::PendingReview->value)->orWhereNotNull('reviewed_at'))->orderByDesc('updated_at')
            : $query->where('status', ProductStatus::PendingReview->value)->orderBy('submitted_at');

        return $query->limit($limit)->get()->map(fn (Product $p) => [
            'id' => $p->id,
            'title' => $p->title,
            'slug' => $p->slug,
            'vendor' => $p->vendor?->name,
            'vendor_slug' => $p->vendor?->slug,
            'category' => $p->category?->name,
            'price' => (string) $p->price,
            'summary' => $p->summary,
            'description' => Str::limit(trim(strip_tags((string) $p->description)), 400),
            'image' => $p->images->first() !== null ? app(ResolvePublicImageVariantAction::class)->execute((int) $p->images->first()->media_file_id, ShopPresenter::CARD_WIDTH) : null,
            'status' => $p->status->value,
            'submitted_at' => $p->submitted_at?->format('Y-m-d H:i'),
            'changes' => $p->review_changes,
            'review_note' => $p->review_note,
            'reviewed_at' => $p->reviewed_at?->format('Y-m-d H:i'),
        ])->values()->all();
    }

    /** The office approves (on sale) or declines with a note (back to draft). */
    public function execute(int $productId, int $officeUserId, bool $approve, ?string $note): Product
    {
        $note = trim((string) $note);
        if (! $approve && $note === '') {
            throw ValidationException::withMessages(['note' => __('shop.error_listing_note')]);
        }
        $product = DB::transaction(function () use ($productId, $officeUserId, $approve, $note) {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            if ($product->status !== ProductStatus::PendingReview) {
                throw ValidationException::withMessages(['decision' => __('shop.error_listing_not_waiting')]);
            }
            $product->forceFill([
                'status' => $approve ? ProductStatus::Active->value : ProductStatus::Draft->value,
                'review_note' => $approve ? null : $note,
                'review_changes' => null,
                'reviewed_by' => $officeUserId,
                'reviewed_at' => now(),
            ])->save();

            return $product;
        });

        app(NotifyBookshopUserAction::class)->vendor(
            (int) $product->vendor_id,
            __($approve ? 'shop.notice_listing_approved_title' : 'shop.notice_listing_declined_title'),
            $approve
                ? __('shop.notice_listing_approved_body', ['title' => $product->title])
                : __('shop.notice_listing_declined_body', ['title' => $product->title, 'note' => $note]),
            '/vendor#products',
            'listing_decided'
        );
        if ($approve) {
            app(CustomerListsAction::class)->notifyIfBack((int) $product->id);
        }
        app(ResolveStorefrontAction::class)->forget((int) $product->vendor_id);

        return $product;
    }

    /**
     * What changed in the D4 fields, for the office's re-review.
     *
     * @return array<string, array{from: ?string, to: ?string}>
     */
    private function changes(Product $product): array
    {
        $out = [];
        foreach (self::WATCHED as $field) {
            if ($product->isDirty($field)) {
                $out[$field] = [
                    'from' => $this->shown($product->getOriginal($field)),
                    'to' => $this->shown($product->getAttribute($field)),
                ];
            }
        }

        return $out;
    }

    private function shown(mixed $value): ?string
    {
        $text = trim(strip_tags((string) $value));

        return $text === '' ? null : Str::limit($text, 200);
    }
}
