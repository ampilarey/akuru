<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use App\Domains\Bookshop\Actions\Shop\PresentShopVendorAction;
use App\Domains\Bookshop\Actions\Vendor\PresentStorefrontDesignerAction;
use App\Domains\Bookshop\Actions\Vendor\PublishStorefrontAction;
use App\Domains\Bookshop\Actions\Vendor\SaveStorefrontDraftAction;
use App\Domains\Bookshop\Http\Controllers\Concerns\AuthorizesVendor;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BOOKSHOP_PLAN slice B4: the storefront designer, part 1 (`/vendor/
 * storefront`). Identity and theme as a form, a live preview of the real
 * public renderer in an iframe, save as draft, publish, roll back. Thin:
 * what may be chosen, what reads, and what goes live are the Actions'.
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
}
