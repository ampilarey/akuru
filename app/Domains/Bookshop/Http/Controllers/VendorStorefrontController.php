<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use App\Domains\Bookshop\Actions\Shop\PresentShopVendorAction;
use App\Domains\Bookshop\Actions\Vendor\ManageVendorCollectionsAction;
use App\Domains\Bookshop\Actions\Vendor\ManageVendorPagesAction;
use App\Domains\Bookshop\Actions\Vendor\PresentSectionsDesignerAction;
use App\Domains\Bookshop\Actions\Vendor\PresentStorefrontDesignerAction;
use App\Domains\Bookshop\Actions\Vendor\PublishStorefrontAction;
use App\Domains\Bookshop\Actions\Vendor\SaveStorefrontDraftAction;
use App\Domains\Bookshop\Actions\Vendor\SaveStorefrontSectionsAction;
use App\Domains\Bookshop\Actions\Vendor\UploadStorefrontImagesAction;
use App\Domains\Bookshop\Http\Controllers\Concerns\AuthorizesVendor;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BOOKSHOP_PLAN slices B4 and B5: the storefront designer (`/vendor/
 * storefront`). Part 1 — identity and theme as a form, a live preview of
 * the real public renderer in an iframe, save as draft, publish, roll
 * back. Part 2 (`/vendor/storefront/sections`) — the home's sections, the
 * menu and SEO, pages, collections and the image library. Thin: what may
 * be chosen, what reads, and what goes live are the Actions'.
 */
class VendorStorefrontController extends Controller
{
    use AuthorizesVendor;

    public function index(Request $request): Response
    {
        $scope = $this->authorizeVendor($request);

        return Inertia::render('Bookshop/VendorStorefront', [
            't' => trans('shop'),
            'vendor' => ['name' => $scope->vendorName, 'slug' => $scope->vendorSlug, 'role' => $scope->role->value],
            'designer' => app(PresentStorefrontDesignerAction::class)->execute($scope),
            'preview_url' => route('vendor.storefront.preview'),
            'public_url' => route('public.shop.vendor', $scope->vendorSlug),
        ]);
    }

    public function saveDraft(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $kilobytes = (int) config('bookshop.storefront.images.max_kilobytes', 5120);
        $data = $request->validate([
            'name_dv' => 'nullable|string|max:120', 'name_ar' => 'nullable|string|max:120',
            'tagline_dv' => 'nullable|string|max:200', 'tagline_ar' => 'nullable|string|max:200',
            'story' => 'nullable|string|max:20000', 'story_dv' => 'nullable|string|max:20000', 'story_ar' => 'nullable|string|max:20000',
            'contact' => 'nullable|array', 'contact.*' => 'nullable|string|max:500',
            'hours' => 'nullable|string|max:500',
            'socials' => 'nullable|array', 'socials.*' => 'nullable|string|max:300',
            'theme' => 'nullable|array',
            'remove_images' => 'nullable|array', 'remove_images.*' => 'string|in:logo,logo_dark,banner',
            'images' => 'nullable|array',
            'images.logo' => 'nullable|file|image|max:'.$kilobytes,
            'images.logo_dark' => 'nullable|file|image|max:'.$kilobytes,
            'images.banner' => 'nullable|file|image|max:'.$kilobytes,
        ]);

        $result = app(SaveStorefrontDraftAction::class)->execute($scope, $data, array_filter((array) ($data['images'] ?? [])));

        return back()->with('success', $result['problems'] === [] ? __('shop.draft_saved_flash') : __('shop.draft_saved_unreadable_flash', ['count' => count($result['problems'])]));
    }

    public function publish(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate(['note' => 'nullable|string|max:200']);

        app(PublishStorefrontAction::class)->publish($scope, $data['note'] ?? null);

        return back()->with('success', __('shop.published_flash'));
    }

    public function rollBack(Request $request, int $version): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);

        app(PublishStorefrontAction::class)->rollBack($scope, $version);

        return back()->with('success', __('shop.rolled_back_flash'));
    }

    /** The real public page, rendered from the draft, for the designer's iframe. */
    public function preview(Request $request)
    {
        $scope = $this->authorizeVendor($request);
        $shop = app(PresentShopVendorAction::class)->execute($scope->vendorSlug, draft: true);
        abort_if($shop === null, 404);

        return view('public.shop.index', [
            'home' => null,
            'products' => app(ListShopProductsAction::class)->execute(['vendor' => $scope->vendorSlug], storefront: true),
            'filters' => ['vendor' => $scope->vendorSlug],
            'options' => ['categories' => [], 'brands' => [], 'sorts' => ListShopProductsAction::SORTS],
            'vendor' => $shop,
            'heading' => $shop['name'],
            'preview' => true,
        ]);
    }

    /* ------------------------------------------------------------ B5: sections, pages, collections, images */

    public function sections(Request $request): Response
    {
        $scope = $this->authorizeVendor($request);

        return Inertia::render('Bookshop/VendorSections', [
            't' => trans('shop'),
            'vendor' => ['name' => $scope->vendorName, 'slug' => $scope->vendorSlug, 'role' => $scope->role->value],
            'designer' => app(PresentSectionsDesignerAction::class)->execute($scope),
            'preview_url' => route('vendor.storefront.preview'),
            'public_url' => route('public.shop.vendor', $scope->vendorSlug),
        ]);
    }

    public function saveSections(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate([
            'sections' => 'nullable|array', 'sections.*' => 'array',
            'navigation' => 'nullable|array', 'navigation.*' => 'array',
            'seo' => 'nullable|array',
        ]);

        app(SaveStorefrontSectionsAction::class)->home($scope, $data);

        return back()->with('success', __('shop.sections_saved_flash'));
    }

    public function storePage(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate(['title' => 'required|string|max:120', 'title_dv' => 'nullable|string|max:120', 'title_ar' => 'nullable|string|max:120', 'slug' => 'nullable|string|max:80']);

        $page = app(ManageVendorPagesAction::class)->create($scope, $data);

        return back()->with('success', __('shop.page_created_flash', ['slug' => $page->slug]));
    }

    public function updatePage(Request $request, int $page): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate(['title' => 'required|string|max:120', 'title_dv' => 'nullable|string|max:120', 'title_ar' => 'nullable|string|max:120', 'sort_order' => 'nullable|integer|min:0|max:1000']);

        app(ManageVendorPagesAction::class)->update($scope, $page, $data);

        return back()->with('success', __('shop.page_saved_flash'));
    }

    public function destroyPage(Request $request, int $page): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);

        app(ManageVendorPagesAction::class)->delete($scope, $page);

        return back()->with('success', __('shop.page_deleted_flash'));
    }

    public function savePageSections(Request $request, int $page): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate(['sections' => 'nullable|array', 'sections.*' => 'array', 'seo' => 'nullable|array']);

        app(SaveStorefrontSectionsAction::class)->page($scope, $page, $data);

        return back()->with('success', __('shop.sections_saved_flash'));
    }

    /** A page under the storefront, rendered from its draft, for the designer's iframe. */
    public function previewPage(Request $request, int $page)
    {
        $scope = $this->authorizeVendor($request);
        $slug = app(ManageVendorPagesAction::class)->slug($scope, $page);
        $shop = app(PresentShopVendorAction::class)->page($scope->vendorSlug, $slug, draft: true);
        abort_if($shop === null, 404);

        return view('public.shop.page', ['vendor' => $shop['vendor'], 'page' => $shop['page'], 'preview' => true]);
    }

    public function saveCollection(Request $request, ?int $collection = null): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate([
            'name' => 'required|string|max:120', 'name_dv' => 'nullable|string|max:120', 'name_ar' => 'nullable|string|max:120',
            'slug' => 'nullable|string|max:80', 'description' => 'nullable|string|max:500',
            'kind' => 'required|string|in:manual,rule',
            'product_ids' => 'nullable|array', 'product_ids.*' => 'integer',
            'rule' => 'nullable|array', 'rule.tags' => 'nullable|array', 'rule.tags.*' => 'string|max:40', 'rule.category_id' => 'nullable|integer',
            'is_active' => 'nullable|boolean', 'sort_order' => 'nullable|integer|min:0|max:1000',
        ]);

        $saved = app(ManageVendorCollectionsAction::class)->save($scope, $collection, $data);

        return back()->with('success', __('shop.collection_saved_flash', ['slug' => $saved->slug]));
    }

    public function destroyCollection(Request $request, int $collection): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);

        app(ManageVendorCollectionsAction::class)->delete($scope, $collection);

        return back()->with('success', __('shop.collection_deleted_flash'));
    }

    public function uploadImages(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $kilobytes = (int) config('bookshop.storefront.images.max_kilobytes', 5120);
        $data = $request->validate(['images' => 'required|array|min:1|max:12', 'images.*' => 'file|image|max:'.$kilobytes, 'alt' => 'nullable|string|max:200']);

        $ids = app(UploadStorefrontImagesAction::class)->upload($scope, array_values($data['images']), $data['alt'] ?? null);

        return back()->with('success', __('shop.images_uploaded_flash', ['count' => count($ids)]));
    }

    public function updateImage(Request $request, int $image): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate(['alt' => 'nullable|string|max:200']);

        app(UploadStorefrontImagesAction::class)->describe($scope, $image, $data['alt'] ?? null);

        return back()->with('success', __('shop.image_saved_flash'));
    }

    public function destroyImage(Request $request, int $image): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);

        app(UploadStorefrontImagesAction::class)->remove($scope, $image);

        return back()->with('success', __('shop.image_removed_flash'));
    }
}
