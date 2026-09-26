<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\VendorPage;
use App\Domains\Bookshop\Models\VendorStorefront;
use App\Domains\Bookshop\Support\SectionTypes;

/**
 * The vendor arranges the home page (BOOKSHOP_PLAN §6.3, §6.4, §6.7): the
 * ordered sections, the storefront menu and the SEO fields go into the
 * draft; a page's sections and SEO into that page's draft. Everything is
 * normalised by `SectionTypes` — nothing typed reaches the page unchecked —
 * and a section type the office has locked for this shop is refused.
 * Publishing (`PublishStorefrontAction`) is what makes a draft public.
 *
 * Owners and staff alike: arranging the page is listing work.
 */
class SaveStorefrontSectionsAction
{
    /**
     * @param  array<string, mixed>  $data  `sections`, `navigation`, `seo`
     */
    public function home(VendorScope $scope, array $data): VendorStorefront
    {
        $storefront = VendorStorefront::query()->firstOrCreate(['vendor_id' => $scope->vendorId]);
        $storefront->update([
            'draft_sections' => SectionTypes::normalize((array) ($data['sections'] ?? []), $scope->vendorId, (array) ($storefront->locked_section_types ?? [])),
            'draft_navigation' => SectionTypes::normalizeNavigation((array) ($data['navigation'] ?? []), $scope->vendorId),
            'draft_seo' => SectionTypes::normalizeSeo((array) ($data['seo'] ?? []), $scope->vendorId),
        ]);

        return $storefront->refresh();
    }

    /**
     * @param  array<string, mixed>  $data  `sections`, `seo`
     */
    public function page(VendorScope $scope, int $pageId, array $data): VendorPage
    {
        $page = VendorPage::query()->where('vendor_id', $scope->vendorId)->whereKey($pageId)->firstOrFail();
        $locked = (array) (VendorStorefront::query()->where('vendor_id', $scope->vendorId)->value('locked_section_types') ?? []);
        $page->update([
            'draft_sections' => SectionTypes::normalize((array) ($data['sections'] ?? []), $scope->vendorId, $locked),
            'seo' => SectionTypes::normalizeSeo((array) ($data['seo'] ?? []), $scope->vendorId),
        ]);

        return $page->refresh();
    }
}
