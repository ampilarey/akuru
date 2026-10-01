<?php

namespace App\Domains\Bookshop\Actions\Orders;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Models\Order;

/**
 * COMMERCE_PARITY_PLAN P8d: on a pre-order's release date the buyer is told
 * it is on its way to being sent, and the shop that it can send it now.
 * Once per order (`preorder_released_at`); run daily by
 * `bookshop:release-preorders`, and safe to run again.
 */
class ReleasePreordersAction
{
    public function releaseDue(): int
    {
        $notify = app(NotifyBookshopUserAction::class);
        $count = 0;
        Order::query()->whereNotNull('ships_from')->whereNull('preorder_released_at')
            ->where('ships_from', '<=', now('Indian/Maldives')->toDateString())
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::CashDue->value, OrderStatus::Processing->value, OrderStatus::NeedsAttention->value])
            ->orderBy('id')->each(function (Order $order) use ($notify, &$count) {
                // Claim it first, so a second run at the same moment tells nobody twice.
                if (Order::query()->whereKey($order->id)->whereNull('preorder_released_at')->update(['preorder_released_at' => now()]) === 0) {
                    return;
                }
                $notify->execute((int) $order->user_id, __('shop.notice_preorder_released_title', ['number' => $order->number]),
                    __('shop.notice_preorder_released_body', ['number' => $order->number]), '/my-orders/'.$order->number, 'order_progress');
                $notify->vendor((int) $order->vendor_id, __('shop.notice_preorder_vendor_title', ['number' => $order->number]),
                    __('shop.notice_preorder_vendor_body', ['number' => $order->number]), '/vendor/orders');
                $count++;
            });

        return $count;
    }
}
