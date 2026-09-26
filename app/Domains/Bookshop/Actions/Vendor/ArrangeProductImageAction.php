<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\ProductImage;
use Illuminate\Support\Facades\DB;

/**
 * Make a photo the card image, or remove it (BOOKSHOP_PLAN §5 "the first is
 * the card image"). Drag-to-order is B7 polish; these two moves are what a
 * vendor needs to get the right photo in front. The photo must belong to a
 * product of the scope's vendor, or it is a 404.
 */
class ArrangeProductImageAction
{
    public function execute(VendorScope $scope, int $imageId, string $move): void
    {
        DB::transaction(function () use ($scope, $imageId, $move) {
            $image = ProductImage::query()
                ->whereHas('product', fn ($q) => $q->where('vendor_id', $scope->vendorId))
                ->findOrFail($imageId);

            if ($move === 'remove') {
                $image->delete();

                return;
            }

            // 'first': this one to the front, the rest keep their order behind it.
            $siblings = ProductImage::query()
                ->where('product_id', $image->product_id)
                ->whereKeyNot($image->id)
                ->orderBy('sort_order')->orderBy('id')
                ->get();
            $image->update(['sort_order' => 0]);
            foreach ($siblings as $index => $sibling) {
                $sibling->update(['sort_order' => $index + 1]);
            }
        });
    }
}
