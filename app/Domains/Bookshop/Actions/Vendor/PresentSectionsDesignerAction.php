<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\VendorStorefront;
use App\Domains\Bookshop\Support\SectionTypes;

/**
 * Everything the sections designer shows (BOOKSHOP_PLAN §6.3–§6.7): the
 * home's draft sections, menu and SEO fields; the pages with their drafts;
 * the collections; the image library; the vendor's products and categories
 * for the pickers; the section schema (what each type may hold); the
 * office's moderation state (a note, a hold, locked types); and the limits.
 *
 * @return array<string, mixed>
 */
class PresentSectionsDesignerAction
{
    public function execute(VendorScope $scope): array
    {
        $storefront = VendorStorefront::query()->where('vendor_id', $scope->vendorId)->first();
        $products = Product::query()->where('vendor_id', $scope->vendorId)->where('status', 'active')->orderBy('title')->get(['id', 'title', 'slug', 'product_category_id', 'tags']);
        $categories = ProductCategory::query()->whereIn('id', $products->pluck('product_category_id')->filter()->unique()->all())->orderBy('name')->get(['id', 'name']);

        return [
            'exists' => $storefront !== null,
            'published_at' => $storefront?->published_at?->toDateTimeString(),
            'sections' => (array) ($storefront?->draft_sections ?? []),
            'navigation' => (array) ($storefront?->draft_navigation ?? []),
            'seo' => ((array) ($storefront?->draft_seo ?? [])) + ['title' => null, 'description' => null, 'image' => null],
            'draft_dirty' => $storefront !== null && (
                ($storefront->draft_sections ?? []) !== ($storefront->published_sections ?? [])
                || ($storefront->draft_navigation ?? []) !== ($storefront->published_navigation ?? [])
                || ($storefront->draft_seo ?? []) !== ($storefront->published_seo ?? [])
            ),
            'moderation' => [
                'held' => $storefront?->isHeld() ?? false,
                'held_at' => $storefront?->held_at?->toDateTimeString(),
                'note' => $storefront?->moderation_note,
                'locked_types' => (array) ($storefront?->locked_section_types ?? []),
            ],
            'pages' => app(ManageVendorPagesAction::class)->list($scope),
            'collections' => app(ManageVendorCollectionsAction::class)->list($scope),
            'library' => app(UploadStorefrontImagesAction::class)->list($scope),
            'products' => $products->map(fn (Product $p) => ['id' => $p->id, 'title' => $p->title, 'slug' => $p->slug, 'category_id' => $p->product_category_id, 'tags' => (array) ($p->tags ?? [])])->values()->all(),
            'categories' => $categories->map(fn (ProductCategory $c) => ['id' => $c->id, 'name' => $c->name])->values()->all(),
            'tags' => $products->flatMap(fn (Product $p) => (array) ($p->tags ?? []))->unique()->sort()->values()->all(),
            'schema' => SectionTypes::schema(),
            'visibilities' => SectionTypes::VISIBILITIES,
            'link_kinds' => SectionTypes::LINK_KINDS,
            'limits' => [
                'sections' => (int) config('bookshop.storefront.max_sections', 20),
                'pages' => (int) config('bookshop.storefront.max_pages', 10),
                'collections' => (int) config('bookshop.storefront.max_collections', 20),
                'images' => (int) config('bookshop.storefront.max_library_images', 60),
                'image_kilobytes' => (int) config('bookshop.storefront.images.max_kilobytes', 5120),
            ],
        ];
    }
}
