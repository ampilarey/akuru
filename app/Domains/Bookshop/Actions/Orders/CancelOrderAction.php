<?php

namespace App\Domains\Bookshop\Actions\Orders;

use App\Domains\Bookshop\Actions\Money\ReverseVendorEarningAction;
use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderEvent;
use App\Domains\Bookshop\Support\Restock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An order cancelled before it left the shop (BOOKSHOP_PLAN §4, §5): by
 * the customer ("cancel before dispatch") or by the shop, with a reason
 * either way. The stock goes back on the shelf, everything paid for the
 * order goes back through `RefundOrderAction`, and the other side is told.
 *
 * Callers resolve the order first — the customer's own, or the vendor's
 * through its `VendorScope` — so this never trusts an id it was handed.
 */
class CancelOrderAction
{
    public function execute(Order $order, int $actorUserId, string $reason, bool $byCustomer): Order
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => __('shop.error_cancel_reason')]);
        }

        $order = DB::transaction(function () use ($order, $actorUserId, $reason, $byCustomer) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $allowed = $byCustomer ? $locked->status->cancellableByCustomer() : $locked->status->cancellableByVendor();
            if (! $allowed) {
                throw ValidationException::withMessages(['order' => __('shop.error_cannot_cancel')]);
            }

            $locked->update([
                'status' => OrderStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancelled_by' => $actorUserId,
                'cancel_reason' => $reason,
            ]);
            OrderEvent::query()->create([
                'order_id' => $locked->id, 'type' => 'cancelled', 'actor_user_id' => $actorUserId, 'note' => $reason,
                'created_at' => now(), 'meta' => ['by' => $byCustomer ? 'customer' : 'vendor'],
            ]);

            foreach ($locked->items as $item) {
                Restock::item($item, (int) $item->quantity);
            }

            app(RefundOrderAction::class)->request($locked, (float) $locked->total, 'Cancelled: '.$reason, $actorUserId);
            // B6: a free order raises no refund, but its earning still goes.
            app(ReverseVendorEarningAction::class)->cancel($locked);

            return $locked->refresh();
        });

        $notify = app(NotifyBookshopUserAction::class);
        if ($byCustomer) {
            $notify->vendor((int) $order->vendor_id, __('shop.notice_customer_cancelled_title'), __('shop.notice_customer_cancelled_body', ['number' => $order->number, 'reason' => $reason]), '/vendor/orders');
        } else {
            $notify->execute((int) $order->user_id, __('shop.notice_vendor_cancelled_title'), __('shop.notice_vendor_cancelled_body', ['number' => $order->number, 'reason' => $reason]), '/my-orders/'.$order->number);
        }

        return $order;
    }
}
