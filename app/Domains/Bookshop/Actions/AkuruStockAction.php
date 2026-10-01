<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * COMMERCE_PARITY_PLAN P6a: the part of a shop's stock Akuru holds to pack.
 * The office records a hand-over (`received_at_akuru`) or a hand-back
 * (`returned_to_vendor`) — a change of place, not of count, so `stock` is
 * untouched and the log line says where it went. A paid order Akuru packs
 * draws from it; a cancellation puts it back.
 */
class AkuruStockAction
{
    public function receive(int $productId, int $quantity, int $officeUserId, ?string $note = null): Product
    {
        return $this->move($productId, abs($quantity), 'received_at_akuru', $officeUserId, $note);
    }

    public function giveBack(int $productId, int $quantity, int $officeUserId, ?string $note = null): Product
    {
        return $this->move($productId, -abs($quantity), 'returned_to_vendor', $officeUserId, $note);
    }

    /** A paid order Akuru packs: its lines leave Akuru's shelf. */
    public function draw(Order $order): void
    {
        if ($order->fulfilled_by !== 'akuru') {
            return;
        }
        foreach ($order->items()->get() as $item) {
            /** @var OrderItem $item */
            if ($item->product_id !== null) {
                Product::query()->whereKey($item->product_id)->where('track_stock', true)
                    // Unsigned: subtracting past zero would be an error in MySQL, not a zero.
                    ->update(['stock_at_akuru' => DB::raw('CASE WHEN stock_at_akuru > '.(int) $item->quantity.' THEN stock_at_akuru - '.(int) $item->quantity.' ELSE 0 END')]);
            }
        }
    }

    /** A cancelled or returned line of an order Akuru packs goes back on Akuru's shelf. */
    public function putBack(OrderItem $item, int $quantity): void
    {
        $order = Order::query()->find($item->order_id, ['id', 'fulfilled_by']);
        if ($order?->fulfilled_by !== 'akuru' || $item->product_id === null || $quantity <= 0) {
            return;
        }
        Product::query()->whereKey($item->product_id)->where('track_stock', true)->increment('stock_at_akuru', $quantity);
    }

    private function move(int $productId, int $delta, string $kind, int $userId, ?string $note): Product
    {
        return DB::transaction(function () use ($productId, $delta, $kind, $userId, $note) {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            $after = (int) $product->stock_at_akuru + $delta;
            if ($delta === 0 || $after < 0 || $after > (int) $product->stock) {
                throw ValidationException::withMessages(['quantity' => __('shop.error_akuru_stock', ['at' => (int) $product->stock_at_akuru, 'stock' => (int) $product->stock])]);
            }
            $product->forceFill(['stock_at_akuru' => $after])->save();
            StockMovement::query()->create([
                'vendor_id' => $product->vendor_id, 'product_id' => $product->id, 'kind' => $kind, 'quantity' => abs($delta),
                'stock_after' => (int) $product->stock, 'note' => mb_substr(trim(__('shop.akuru_stock_note', ['at' => $after]).' '.(string) $note), 0, 255),
                'user_id' => $userId, 'created_at' => now(),
            ]);

            return $product;
        });
    }
}
