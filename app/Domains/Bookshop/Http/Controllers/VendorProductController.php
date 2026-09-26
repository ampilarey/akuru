<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Vendor\ArrangeProductImageAction;
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

    /**
     * @return list<\Illuminate\Http\UploadedFile>
     */
    private function photos(Request $request): array
    {
        return array_values(array_filter((array) $request->file('photos', [])));
    }
}
