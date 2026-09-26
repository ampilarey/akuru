<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\Money\RecordVendorEarningAction;
use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Actions\Orders\CancelOrderAction;
use App\Domains\Bookshop\Actions\Orders\RefundOrderAction;
use App\Domains\Bookshop\Actions\Orders\SendOrderMessageAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Enums\ReturnStatus;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderEvent;
use App\Domains\Bookshop\Models\OrderReturn;
use App\Domains\Bookshop\Support\OrderView;
use App\Domains\Bookshop\Support\Restock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The shop works an order (BOOKSHOP_PLAN §5 "Orders"): processing → ready
 * to collect or dispatched (with the carrier and a tracking note) →
 * delivered or collected; cancel with a reason before it leaves; answer a
 * return; write to the customer. Owners and staff alike ("they can list
 * products and handle orders"). Every order is found through the scope.
 */
class FulfilVendorOrderAction
{
    /**
     * @param  array{carrier?: ?string, tracking_note?: ?string}  $data
     */
    public function advance(VendorScope $scope, int $orderId, string $to, array $data = []): Order
    {
        $order = DB::transaction(function () use ($scope, $orderId, $to, $data) {
            $order = Order::query()->where('vendor_id', $scope->vendorId)->whereKey($orderId)->lockForUpdate()->firstOrFail();
            if (! in_array($to, OrderView::nextSteps($order), true)) {
                throw ValidationException::withMessages(['status' => __('shop.error_step')]);
            }

            $fields = ['status' => $to, $to.'_at' => now()];
            if ($to === 'dispatched') {
                $fields['carrier'] = trim((string) ($data['carrier'] ?? '')) ?: null;
                $fields['tracking_note'] = trim((string) ($data['tracking_note'] ?? '')) ?: null;
            }
            $order->update($fields);
            OrderEvent::query()->create([
                'order_id' => $order->id, 'type' => $to, 'actor_user_id' => $scope->userId, 'created_at' => now(),
                'note' => $to === 'dispatched' ? trim(($fields['carrier'] ?? '').' '.($fields['tracking_note'] ?? '')) ?: null : null,
            ]);
            if ($to === 'delivered') {
                // B6: the return window starts; the earning matures at its end.
                app(RecordVendorEarningAction::class)->onDelivered($order->refresh());
            }

            return $order->refresh();
        });

        if (in_array($to, ['ready', 'dispatched', 'delivered'], true)) {
            $key = $to === 'delivered' && $order->isCollection() ? 'collected' : $to;
            app(NotifyBookshopUserAction::class)->execute(
                (int) $order->user_id,
                __('shop.notice_'.$key.'_title', ['number' => $order->number]),
                __('shop.notice_'.$key.'_body', ['number' => $order->number, 'vendor' => $scope->vendorName, 'tracking' => $order->tracking_note ?? '']),
                '/my-orders/'.$order->number,
            );
        }

        return $order;
    }

    public function cancel(VendorScope $scope, int $orderId, string $reason): Order
    {
        $order = Order::query()->where('vendor_id', $scope->vendorId)->whereKey($orderId)->with(['checkout', 'items'])->firstOrFail();

        return app(CancelOrderAction::class)->execute($order, $scope->userId, $reason, byCustomer: false);
    }

    public function message(VendorScope $scope, int $orderId, string $body): int
    {
        $order = Order::query()->where('vendor_id', $scope->vendorId)->whereKey($orderId)->firstOrFail();

        return app(SendOrderMessageAction::class)->execute($order, $scope->userId, $body);
    }

    /**
     * Accept a return — the money goes back at once for wallet and
     * transfer, or to the office for a card — or decline it with a reason.
     * An item the shop can sell again goes back on the shelf. When the
     * item was the shop's fault the delivery fee goes back too, once.
     */
    public function decideReturn(VendorScope $scope, int $returnId, bool $accept, bool $restock, ?string $note): OrderReturn
    {
        $return = DB::transaction(function () use ($scope, $returnId, $accept, $restock, $note) {
            $return = OrderReturn::query()->whereKey($returnId)
                ->whereHas('order', fn ($q) => $q->where('vendor_id', $scope->vendorId))
                ->lockForUpdate()->firstOrFail();
            if ($return->status !== ReturnStatus::Requested) {
                throw ValidationException::withMessages(['return' => __('shop.error_return_decided')]);
            }
            $note = trim((string) $note) ?: null;
            if (! $accept && $note === null) {
                throw ValidationException::withMessages(['note' => __('shop.error_decline_reason')]);
            }
            $order = $return->order()->with(['checkout', 'returns', 'vendor'])->firstOrFail();

            $delivery = $accept && $return->reason->shopsFault() && ! OrderView::deliveryRefunded($order);
            $return->update([
                'status' => ($accept ? ReturnStatus::Accepted : ReturnStatus::Declined)->value,
                'refunds_delivery' => $delivery,
                'restocked' => $accept && $restock,
                'decided_by' => $scope->userId,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);
            OrderEvent::query()->create(['order_id' => $order->id, 'type' => $accept ? 'return_accepted' : 'return_declined', 'actor_user_id' => $scope->userId, 'created_at' => now(), 'note' => $note]);

            if ($accept) {
                if ($restock) {
                    Restock::item($return->item, (int) $return->quantity);
                }
                $amount = (float) $return->refund_amount + ($delivery ? (float) $order->delivery_fee : 0.0);
                app(RefundOrderAction::class)->request($order, $amount, 'Return: '.$return->item->title, $scope->userId, $return->id);
            }

            return $return->refresh();
        });

        $order = $return->order;
        app(NotifyBookshopUserAction::class)->execute(
            (int) $order->user_id,
            __($accept ? 'shop.notice_return_accepted_title' : 'shop.notice_return_declined_title'),
            __($accept ? 'shop.notice_return_accepted_body' : 'shop.notice_return_declined_body', ['number' => $order->number, 'note' => (string) $return->decision_note]),
            '/my-orders/'.$order->number,
        );

        return $return;
    }
}
