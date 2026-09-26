<?php

namespace App\Domains\Bookshop\Actions\Checkout;

use App\Domains\Bookshop\Actions\Money\RecordVendorEarningAction;
use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Enums\CheckoutStatus;
use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderEvent;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Commerce\Actions\RecordDiscountRedemptionAction;
use Illuminate\Support\Facades\DB;

/**
 * Money arrived (BOOKSHOP_PLAN §8): the one path from a paid checkout to
 * orders a vendor can fulfil. Idempotent — only a pending checkout flips,
 * inside a lock, so a webhook that arrives twice decrements stock once.
 *
 * Stock is decremented here, not at reservation. A reservation that lapsed
 * before the money arrived can find the stock gone: that order is still
 * paid, but marked `needs_attention` and the vendor and office are told —
 * never a silent oversell.
 */
class MarkCheckoutPaidAction
{
    public function execute(int $checkoutId, string $how, ?int $actorUserId = null, ?int $paymentId = null): ?BookshopCheckout
    {
        $paid = DB::transaction(function () use ($checkoutId, $how, $actorUserId, $paymentId) {
            $checkout = BookshopCheckout::query()->whereKey($checkoutId)->lockForUpdate()->first();
            if ($checkout === null || $checkout->status !== CheckoutStatus::PendingPayment) {
                return null;
            }

            $checkout->status = CheckoutStatus::Paid;
            $checkout->paid_at = now();
            if ($paymentId !== null) {
                $checkout->payment_id = $paymentId;
            }
            $checkout->save();

            $attention = [];
            foreach ($checkout->orders as $order) {
                /** @var Order $order */
                $short = $this->takeStock($order);
                $order->status = $short === [] ? OrderStatus::Paid : OrderStatus::NeedsAttention;
                $order->paid_at = now();
                $order->save();
                OrderEvent::query()->create(['order_id' => $order->id, 'type' => 'paid', 'actor_user_id' => $actorUserId, 'created_at' => now(), 'meta' => ['how' => $how]]);
                // B6: what the vendor earned on it, from this moment.
                app(RecordVendorEarningAction::class)->execute($order);
                if ($short !== []) {
                    OrderEvent::query()->create(['order_id' => $order->id, 'type' => 'needs_attention', 'created_at' => now(), 'note' => 'Stock ran out before payment: '.implode(', ', $short)]);
                    $attention[] = $order;
                }
            }

            $checkout->reservations()->delete();
            app(RecordDiscountRedemptionAction::class)->transition('bookshop_checkout', $checkout->id, 'confirmed');

            return ['checkout' => $checkout, 'attention' => $attention];
        });

        if ($paid === null) {
            return null;
        }

        $this->tell($paid['checkout'], $paid['attention']);

        return $paid['checkout'];
    }

    /**
     * @return list<string> the titles that ran short
     */
    private function takeStock(Order $order): array
    {
        $short = [];
        foreach ($order->items as $item) {
            /** @var OrderItem $item */
            $product = $item->product_id !== null ? Product::query()->whereKey($item->product_id)->lockForUpdate()->first() : null;
            if ($product === null || ! $product->track_stock) {
                continue;
            }
            if ($item->product_variant_id !== null) {
                $variant = ProductVariant::query()->whereKey($item->product_variant_id)->lockForUpdate()->first();
                if ($variant === null) {
                    continue;
                }
                if ($variant->stock < $item->quantity) {
                    $short[] = $item->title.' ('.$item->variant_name.')';
                }
                $variant->update(['stock' => max(0, $variant->stock - $item->quantity)]);
            } else {
                if ($product->stock < $item->quantity) {
                    $short[] = $item->title;
                }
                $product->update(['stock' => max(0, $product->stock - $item->quantity)]);
            }
        }

        return $short;
    }

    /**
     * @param  list<Order>  $attention
     */
    private function tell(BookshopCheckout $checkout, array $attention): void
    {
        $notify = app(NotifyBookshopUserAction::class);
        $notify->execute(
            (int) $checkout->user_id,
            __('shop.notice_paid_title'),
            __('shop.notice_paid_body', ['number' => $checkout->number]),
            '/my-orders',
        );
        foreach ($checkout->orders as $order) {
            $notify->vendor((int) $order->vendor_id, __('shop.notice_vendor_order_title'), __('shop.notice_vendor_order_body', ['number' => $order->number]), '/vendor');
        }
        foreach ($attention as $order) {
            $notify->vendor((int) $order->vendor_id, __('shop.notice_attention_title'), __('shop.notice_attention_body', ['number' => $order->number]), '/vendor');
            $notify->office(__('shop.notice_attention_title'), __('shop.notice_attention_body', ['number' => $order->number]), '/admin/bookshop');
        }
    }
}
