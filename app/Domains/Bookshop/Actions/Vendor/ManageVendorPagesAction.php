<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\VendorPage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A shop's own pages under its storefront (BOOKSHOP_PLAN §6.4): About,
 * Delivery, Returns, Bulk orders for schools, Contact — at
 * `/shop/<vendor>/p/<slug>`, built from the same sections as the home.
 * The slug is fixed at creation (it is the page's address, and the menu
 * points at it); the titles and order may change. A page's draft goes
 * public with the storefront's next publish.
 */
class ManageVendorPagesAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(VendorScope $scope): array
    {
        return VendorPage::query()->where('vendor_id', $scope->vendorId)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (VendorPage $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'title' => $p->title,
                'title_dv' => $p->title_dv,
                'title_ar' => $p->title_ar,
                'sort_order' => (int) $p->sort_order,
                'sections' => (array) ($p->draft_sections ?? []),
                'seo' => ((array) ($p->seo ?? [])) + ['title' => null, 'description' => null, 'image' => null],
                'published_at' => $p->published_at?->toDateTimeString(),
                'draft_dirty' => $p->draft_sections !== $p->published_sections,
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(VendorScope $scope, array $data): VendorPage
    {
        $max = (int) config('bookshop.storefront.max_pages', 10);
        if (VendorPage::query()->where('vendor_id', $scope->vendorId)->count() >= $max) {
            throw ValidationException::withMessages(['title' => __('shop.error_too_many_pages', ['max' => $max])]);
        }
        $slug = Str::slug(mb_substr(trim((string) (($data['slug'] ?? '') ?: ($data['title'] ?? ''))), 0, 80));
        if ($slug === '' || VendorPage::query()->where('vendor_id', $scope->vendorId)->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => __('shop.error_slug_taken', ['slug' => $slug])]);
        }

        return VendorPage::query()->create([
            'vendor_id' => $scope->vendorId,
            'slug' => $slug,
            'sort_order' => (int) VendorPage::query()->where('vendor_id', $scope->vendorId)->max('sort_order') + 1,
            'draft_sections' => [],
        ] + $this->titles($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(VendorScope $scope, int $pageId, array $data): VendorPage
    {
        $page = VendorPage::query()->where('vendor_id', $scope->vendorId)->whereKey($pageId)->firstOrFail();
        $page->update($this->titles($data) + ['sort_order' => is_numeric($data['sort_order'] ?? null) ? max(0, (int) $data['sort_order']) : $page->sort_order]);

        return $page->refresh();
    }

    /** One page's slug, for the vendor's own draft preview of it. */
    public function slug(VendorScope $scope, int $pageId): string
    {
        return (string) VendorPage::query()->where('vendor_id', $scope->vendorId)->whereKey($pageId)->firstOrFail()->slug;
    }

    public function delete(VendorScope $scope, int $pageId): void
    {
        VendorPage::query()->where('vendor_id', $scope->vendorId)->whereKey($pageId)->firstOrFail()->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, ?string>
     */
    private function titles(array $data): array
    {
        $text = fn (mixed $v) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, 120) : null;
        $title = $text($data['title'] ?? null);
        if ($title === null) {
            throw ValidationException::withMessages(['title' => __('shop.error_page_title_required')]);
        }

        return ['title' => $title, 'title_dv' => $text($data['title_dv'] ?? null), 'title_ar' => $text($data['title_ar'] ?? null)];
    }
}
