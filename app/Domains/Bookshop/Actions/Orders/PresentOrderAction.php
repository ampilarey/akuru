<?php

namespace App\Domains\Bookshop\Actions\Orders;

use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderEvent;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\OrderRefund;
use App\Domains\Bookshop\Models\OrderReturn;
use App\Domains\Bookshop\Support\OrderView;
use App\Domains\Bookshop\Support\ShopPresenter;

/**
 * One order as its customer reads it (BOOKSHOP_PLAN §4 "My orders"): the
 * status trail, the lines, the delivery, and the receipt — with a tax line
 * and the vendor's TIN only when the vendor is GST-registered (decision 4).
 * Null for anyone else's order.
 */
class PresentOrderAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(int $userId, string $number): ?array
    {
        $order = Order::query()->where('user_id', $userId)->where('number', $number)->with(['vendor', 'items', 'events', 'checkout', 'returns.item', 'refunds'])->first();
        if ($order === null) {
            return null;
        }
        $returnsOpen = OrderView::returnsOpen($order);

        return self::summary($order) + [
            // B3: where it is, and what the customer can still do.
            'collection' => $order->isCollection(),
            'carrier' => $order->carrier,
            'tracking_note' => $order->tracking_note,
            'steps' => [
                'paid' => $order->paid_at?->toDateTimeString(),
                'processing' => $order->processing_at?->toDateTimeString(),
                'ready' => $order->ready_at?->toDateTimeString(),
                'dispatched' => $order->dispatched_at?->toDateTimeString(),
                'delivered' => $order->delivered_at?->toDateTimeString(),
            ],
            'cancelled_at' => $order->cancelled_at?->toDateTimeString(),
            'cancel_reason' => $order->cancel_reason,
            'cancelled_by_customer' => $order->cancelled_by !== null && (int) $order->cancelled_by === (int) $order->user_id,
            'can_cancel' => $order->status->cancellableByCustomer(),
            'returns_open' => $returnsOpen,
            'returns_until' => OrderView::returnsUntil($order)?->toDateString(),
            'return_conditions' => $order->vendor?->return_conditions,
            'returns' => $order->returns->map(fn (OrderReturn $r) => OrderView::returnRow($r))->values()->all(),
            'refunds' => $order->refunds->map(fn (OrderRefund $r) => OrderView::refund($r))->values()->all(),
            'message_thread_id' => $order->message_thread_id,
            'items' => $order->items->map(fn (OrderItem $i) => [
                'id' => $i->id,
                'returnable' => $returnsOpen ? OrderView::returnable($i, $order->returns) : 0,
                'title' => $i->title,
                'variant' => $i->variant_name,
                'sku' => $i->sku,
                'unit_price' => (string) $i->unit_price,
                'quantity' => (int) $i->quantity,
                'line_total' => (string) $i->line_total,
                'tax_class' => $i->tax_class->value,
                'tax_amount' => (string) $i->tax_amount,
            ])->values()->all(),
            'events' => $order->events->map(fn (OrderEvent $e) => [
                'type' => $e->type,
                'note' => $e->note,
                'at' => $e->created_at?->toDateTimeString(),
            ])->values()->all(),
            'address' => $order->address_snapshot,
            'notes' => $order->notes,
            'checkout_number' => $order->checkout?->number,
            'payment_method' => $order->checkout?->payment_method->value,
            'receipt' => [
                'goods' => (string) $order->subtotal,
                'discount' => (string) $order->discount,
                'delivery' => (string) $order->delivery_fee,
                'total' => (string) $order->total,
                'tax_shown' => (bool) $order->tax_shown,
                'tax' => (string) $order->tax,
                'vendor_tin' => $order->vendor_tin,
                'vendor_legal_name' => $order->vendor?->legal_name ?: $order->vendor?->name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Order $order): array
    {
        return [
            'number' => $order->number,
            'status' => $order->status->value,
            'vendor' => ShopPresenter::vendor($order->vendor),
            'delivery' => [
                'kind' => $order->delivery_kind->value,
                'name' => $order->delivery_name,
                'fee' => (string) $order->delivery_fee,
                'carrier_paid' => (bool) $order->delivery_carrier_paid,
                'handling_days' => (int) $order->delivery_handling_days,
            ],
            'total' => (string) $order->total,
            'currency' => $order->currency,
            'item_count' => (int) ($order->relationLoaded('items') ? $order->items->sum('quantity') : $order->items()->sum('quantity')),
            'placed_at' => $order->created_at?->toDateTimeString(),
            'paid_at' => $order->paid_at?->toDateTimeString(),
        ];
    }
}
