<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Vendor\ArrangeProductImageAction;
use App\Domains\Bookshop\Actions\Vendor\BulkVendorProductsAction;
use App\Domains\Bookshop\Actions\Vendor\SaveVendorProductAction;
use App\Domains\Bookshop\Http\Controllers\Concerns\AuthorizesVendor;
use App\Domains\Bookshop\Http\ProductRules;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * BOOKSHOP_PLAN §5: a vendor's products. The scope decides whose products;
 * `SaveVendorProductAction` decides what a valid one is.
 */
class VendorProductController extends Controller
{
    use AuthorizesVendor;

    public function store(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate(ProductRules::rules());

        app(SaveVendorProductAction::class)->execute($scope, $data, null, $this->photos($request));

        return back()->with('success', __('shop.product_saved_flash'));
    }

    public function update(Request $request, int $product): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate(ProductRules::rules());

        app(SaveVendorProductAction::class)->execute($scope, $data, $product, $this->photos($request));

        return back()->with('success', __('shop.product_saved_flash'));
    }

    public function arrangeImage(Request $request, int $image): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate(['move' => 'required|string|in:first,remove']);

        app(ArrangeProductImageAction::class)->execute($scope, $image, $data['move']);

        return back()->with('success', __('shop.photos_updated_flash'));
    }

    /** B8: a draft copy to make a similar product quickly. */
    public function duplicate(Request $request, int $product): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);

        $copy = app(BulkVendorProductsAction::class)->duplicate($scope, $product);

        return back()->with('success', __('shop.duplicated_flash', ['title' => $copy->title]));
    }

    /** B8: put a selection on sale, back to draft, or into the archive. */
    public function bulk(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'status' => 'required|string|in:'.implode(',', BulkVendorProductsAction::STATUSES),
        ]);

        $changed = app(BulkVendorProductsAction::class)->setStatus($scope, $data['ids'], $data['status']);

        return back()->with('success', __('shop.bulk_done_flash', ['count' => $changed]));
    }

    /**
     * @return list<\Illuminate\Http\UploadedFile>
     */
    private function photos(Request $request): array
    {
        return array_values(array_filter((array) $request->file('photos', [])));
    }
}
