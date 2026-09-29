<?php

namespace App\Domains\Bookshop\Actions\Cart;

use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

/**
 * "Buy again" (STATUS §5ld): the items of one of the customer's own orders
 * go back into the cart in the quantities ordered, at today's price, through
 * the same `SaveCartItemAction` as a single add. An item no longer for sale,
 * sold out, or whose option is gone is skipped and named; the rest go in.
 * Own orders only — the order is found by the signed-in user's id.
 */
class BuyAgainAction
{
    public function __construct(private SaveCartItemAction $items) {}

    public function order(int $userId, string $number): ?Order
    {
        return Order::query()->where('user_id', $userId)->where('number', $number)->with(['items.product'])->first();
    }

    /**
     * @return array{added: int, skipped: list<string>}
     */
    public function execute(Cart $cart, Order $order): array
    {
        $added = 0;
        $skipped = [];

        foreach ($order->items as $item) {
            /** @var OrderItem $item */
            $label = trim($item->title.($item->variant_name ? ' — '.$item->variant_name : ''));
            // An option that is gone is not swapped for the plain product.
            $variantGone = $item->product_variant_id !== null
                && ! ProductVariant::query()->whereKey($item->product_variant_id)->where('product_id', $item->product_id)->where('is_active', true)->exists();
            if ($item->product === null || $variantGone) {
                $skipped[] = $label;

                continue;
            }
            try {
                $this->items->add($cart, $item->product->slug, $item->product_variant_id !== null ? (int) $item->product_variant_id : null, max(1, (int) $item->quantity));
                $added++;
            } catch (ValidationException) {
                $skipped[] = $label;
            }
        }

        return ['added' => $added, 'skipped' => $skipped];
    }
}
