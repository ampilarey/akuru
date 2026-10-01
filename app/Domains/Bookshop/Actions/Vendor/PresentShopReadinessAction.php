<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\ListingApprovalAction;
use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Enums\ProductStatus;
use App\Domains\Bookshop\Enums\VendorMemberRole;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\VendorMember;
use App\Domains\Bookshop\Models\VendorPage;
use App\Domains\Bookshop\Models\VendorStorefront;
use App\Domains\Bookshop\Support\VendorIdentity;
use App\Domains\Identity\Actions\IdentityVerificationAction;

/**
 * STATUS §5mq: "why can't customers see my changes?" — answered on the
 * portal's first screen. The owner, 2026-10-01: shop owners' changes to
 * their page were not showing. On production not one product was on sale
 * in the whole Bookstore, so every product section (featured, new arrivals,
 * collection, categories, best sellers) was left off the live page as
 * empty, and the page showed a theme and a logo over nothing. Three things
 * stand between a shop's work and its customers, and each was explained
 * only where it happens — a refused save, a status label in a long list, a
 * small line in the designer:
 *
 *  1. the owner's **ID card**, which the office checks before anything can
 *     go on sale (COMMERCE_PARITY_PLAN P2);
 *  2. each product's **listing approval** by the office (P4), unless the
 *     office marks the shop trusted;
 *  3. the page design's **Publish** (B4/B5): saved changes are a draft.
 *
 * This gathers the three in one place, with what to do about each.
 */
class PresentShopReadinessAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(VendorScope $scope): array
    {
        $counts = Product::query()->where('vendor_id', $scope->vendorId)
            ->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $onSale = ListShopProductsAction::forSale()->where('vendor_id', $scope->vendorId)->count();
        $storefront = VendorStorefront::query()->where('vendor_id', $scope->vendorId)->first();

        return [
            'id' => $this->identity($scope),
            'trusted' => app(ListingApprovalAction::class)->trusted($scope->vendorId),
            'products' => [
                'on_sale' => $onSale,
                'waiting' => (int) ($counts[ProductStatus::PendingReview->value] ?? 0),
                'drafts' => (int) ($counts[ProductStatus::Draft->value] ?? 0),
                'total' => (int) $counts->sum(),
            ],
            'storefront' => [
                'published' => $storefront?->isPublished() ?? false,
                'held' => $storefront?->isHeld() ?? false,
                'dirty' => $storefront !== null && $this->dirty($storefront),
            ],
        ];
    }

    /**
     * Where the owners' ID card stands: verified, or the furthest along of the
     * owners' latest — waiting for the office, refused with a note, or not sent.
     *
     * @return array{status: string, note: ?string}
     */
    private function identity(VendorScope $scope): array
    {
        if (VendorIdentity::verified($scope->vendorId)) {
            return ['status' => 'verified', 'note' => null];
        }
        $statuses = VendorMember::query()->where('vendor_id', $scope->vendorId)->where('role', VendorMemberRole::Owner->value)->orderBy('id')->pluck('user_id')
            ->map(fn ($id) => app(IdentityVerificationAction::class)->status((int) $id, 'vendor'));
        foreach (['pending', 'rejected'] as $status) {
            $found = $statuses->firstWhere('status', $status);
            if ($found !== null) {
                return ['status' => $status, 'note' => $found['note'] ?? null];
            }
        }

        return ['status' => 'none', 'note' => null];
    }

    /** Saved design work customers do not see yet — the storefront's or any page's. */
    private function dirty(VendorStorefront $storefront): bool
    {
        foreach (['identity', 'theme', 'sections', 'navigation', 'seo'] as $part) {
            if (($storefront->{'draft_'.$part} ?? []) !== ($storefront->{'published_'.$part} ?? [])) {
                return true;
            }
        }

        return VendorPage::query()->where('vendor_id', $storefront->vendor_id)->get(['draft_sections', 'published_sections'])
            ->contains(fn (VendorPage $p) => ($p->draft_sections ?? []) !== ($p->published_sections ?? []));
    }
}
