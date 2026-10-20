<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Support\PrintDesk;
use App\Domains\Bookshop\Support\PrintLine;
use Illuminate\Validation\ValidationException;

/**
 * NAME_TAGS_PLAN §3.8 (NT4): an approved job into its owner's cart — one
 * line, the print product, quantity = the job's sheets, priced by the held
 * approval. Adding it again finds the same line. A job already in an order
 * that is not over is not sold twice.
 */
class AddPrintJobToCartAction
{
    public function execute(Cart $cart, PrintJob $job, int $userId): CartItem
    {
        $why = PrintLine::blocker($job, (int) $job->sheets, $userId);
        if ($why !== null) {
            throw ValidationException::withMessages(['job' => __($why)]);
        }
        // PP2: the job's own print product.
        $product = $job->product_id !== null ? Product::query()->find($job->product_id) : PrintDesk::product();
        if ($product === null || ! ListShopProductsAction::forSale()->whereKey($product->id)->exists()) {
            throw ValidationException::withMessages(['job' => __('print.error_cart_service_off')]);
        }
        if (self::inOpenOrder((int) $job->id)) {
            throw ValidationException::withMessages(['job' => __('print.error_cart_already_ordered')]);
        }

        return CartItem::query()->firstOrCreate(
            ['cart_id' => $cart->id, 'print_job_id' => $job->id],
            ['product_id' => $product->id, 'product_variant_id' => null, 'quantity' => (int) $job->sheets],
        );
    }

    /** Is the job on an order that is still alive (anything but expired or cancelled)? */
    public static function inOpenOrder(int $jobId): bool
    {
        return OrderItem::query()->where('print_job_id', $jobId)
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['expired', 'cancelled']))->exists();
    }
}
