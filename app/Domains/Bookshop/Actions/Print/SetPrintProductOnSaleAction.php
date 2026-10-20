<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Enums\ProductStatus;
use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintProducts;
use Illuminate\Validation\ValidationException;

/**
 * PRINT_PRODUCTS_PLAN PP2 (PD3): one print product on sale or off. On sale
 * only while the desk is on, and only with a layout of its own to print on;
 * off at any time. Name tags is the desk's own switch, not this.
 */
class SetPrintProductOnSaleAction
{
    public function execute(string $kind, bool $onSale): Product
    {
        if (! PrintProducts::valid($kind)) {
            throw ValidationException::withMessages(['product' => __('print.error_product_unknown')]);
        }
        if ($kind === PrintProducts::DEFAULT) {
            // Name tags go on and off with the desk's own switch (NT1).
            throw ValidationException::withMessages(['product' => __('print.error_product_name_tags')]);
        }
        PrintDesk::ensure();
        if ($onSale && ! PrintDesk::settings()['enabled']) {
            throw ValidationException::withMessages(['product' => __('print.error_product_desk_off')]);
        }
        if ($onSale && ! PrintLayout::query()->where('is_active', true)->where('print_kind', $kind)->exists()) {
            throw ValidationException::withMessages(['product' => __('print.error_product_no_layout', ['product' => PrintProducts::title($kind, app()->getLocale())])]);
        }
        $product = PrintProducts::product($kind);
        $product->status = $onSale ? ProductStatus::Active : ProductStatus::Draft;
        $product->save();

        return $product;
    }
}
