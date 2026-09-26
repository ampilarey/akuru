<?php

namespace App\Domains\Bookshop\Actions\Orders;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Enums\ReturnReason;
use App\Domains\Bookshop\Enums\ReturnStatus;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderEvent;
use App\Domains\Bookshop\Models\OrderReturn;
use App\Domains\Bookshop\Support\OrderView;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A customer asks to send part of a delivered order back (BOOKSHOP_PLAN §4
 * "request a return within the window"; decision 8: seven days unless the
 * shop offers longer). Only their own order, only after it arrived, only
 * inside the window, never more units than they have not already sent
 * back. The shop is told and answers in its portal.
 */
class RequestReturnAction
{
    public function execute(int $userId, string $orderNumber, int $itemId, int $quantity, string $reason, ?string $note): OrderReturn
    {
        $why = ReturnReason::tryFrom($reason);
        if ($why === null) {
            throw ValidationException::withMessages(['reason' => __('shop.error_return_reason')]);
        }

        $return = DB::transaction(function () use ($userId, $orderNumber, $itemId, $quantity, $why, $note) {
            $order = Order::query()->where('user_id', $userId)->where('number', $orderNumber)->with(['vendor', 'returns'])->lockForUpdate()->firstOrFail();
            if (! OrderView::returnsOpen($order)) {
                throw ValidationException::withMessages(['order' => __('shop.error_return_window')]);
            }
            $item = $order->items()->whereKey($itemId)->firstOrFail();
            $left = OrderView::returnable($item, $order->returns);
            if ($quantity < 1 || $quantity > $left) {
                throw ValidationException::withMessages(['quantity' => __('shop.error_return_quantity', ['count' => $left])]);
            }

            $return = OrderReturn::query()->create([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'quantity' => $quantity,
                'reason' => $why->value,
                'note' => trim((string) $note) ?: null,
                'status' => ReturnStatus::Requested->value,
                'refund_amount' => OrderView::returnValue($order, $item, $quantity),
                'requested_by' => $userId,
            ]);
            OrderEvent::query()->create(['order_id' => $order->id, 'type' => 'return_requested', 'actor_user_id' => $userId, 'created_at' => now(), 'note' => $quantity.' × '.$item->title.' ('.$why->value.')']);

            return $return;
        });

        $order = $return->order;
        app(NotifyBookshopUserAction::class)->vendor(
            (int) $order->vendor_id,
            __('shop.notice_return_requested_title'),
            __('shop.notice_return_requested_body', ['number' => $order->number]),
            '/vendor/orders',
        );

        return $return;
    }
}
