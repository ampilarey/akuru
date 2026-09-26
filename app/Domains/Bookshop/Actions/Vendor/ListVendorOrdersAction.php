<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Enums\CheckoutPaymentMethod;
use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Enums\ReturnStatus;
use App\Domains\Bookshop\Enums\SlipStatus;
use App\Domains\Bookshop\Models\BankTransferSlip;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderEvent;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\OrderRefund;
use App\Domains\Bookshop\Models\OrderReturn;
use App\Domains\Bookshop\Support\OrderView;

/**
 * The shop's order queue (BOOKSHOP_PLAN §5 "Queue by status; open an
 * order"). Paid orders and everything after; a bank-transfer order still
 * waiting for its money appears only when a slip is in and the shop may
 * confirm it (a checkout that is the shop's alone). Expired checkouts
 * never show. The customer's phone and address are masked once the order
 * has closed and the return window passed (decision 15).
 */
class ListVendorOrdersAction
{
    /**
     * @param  array{status?: ?string, q?: ?string}  $filters
     * @return array{orders: list<array<string, mixed>>, counts: array<string, int>}
     */
    public function execute(VendorScope $scope, array $filters = [], int $limit = 200): array
    {
        $base = Order::query()->where('vendor_id', $scope->vendorId)
            ->where(fn ($q) => $q->whereNotIn('status', [OrderStatus::PendingPayment->value, OrderStatus::Expired->value])
                ->orWhere(fn ($pending) => $pending->where('status', OrderStatus::PendingPayment->value)
                    ->whereHas('checkout', fn ($c) => $c->where('payment_method', CheckoutPaymentMethod::BankTransfer->value)
                        ->whereHas('slips', fn ($s) => $s->where('status', SlipStatus::Waiting->value)))));

        $counts = (clone $base)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();
        $counts['returns'] = OrderReturn::query()->where('status', ReturnStatus::Requested->value)
            ->whereHas('order', fn ($q) => $q->where('vendor_id', $scope->vendorId))->count();

        $status = $filters['status'] ?? null;
        $orders = (clone $base)
            ->when($status === 'returns', fn ($q) => $q->whereHas('returns', fn ($r) => $r->where('status', ReturnStatus::Requested->value)))
            ->when($status !== null && $status !== 'returns', fn ($q) => $q->where('status', $status))
            ->when(($filters['q'] ?? '') !== '', fn ($q) => $q->where('number', 'like', '%'.$filters['q'].'%'))
            // B8: an export by date — orders placed from … to … (Maldives dates).
            ->when(! empty($filters['from']), fn ($q) => $q->where('created_at', '>=', $filters['from'].' 00:00:00'))
            ->when(! empty($filters['to']), fn ($q) => $q->where('created_at', '<=', $filters['to'].' 23:59:59'))
            ->with(['items', 'events', 'returns.item', 'refunds', 'vendor', 'checkout.orders', 'checkout.slips'])
            ->orderByDesc('id')->limit($limit)->get();

        $userModel = config('auth.providers.users.model');
        $people = $userModel::query()->whereIn('id', $orders->pluck('user_id')->unique()->all())->get(['id', 'name'])->keyBy('id');

        return [
            'orders' => $orders->map(fn (Order $o) => $this->row($o, $people->get($o->user_id)?->name))->values()->all(),
            'counts' => $counts,
        ];
    }

    /**
     * B8: one row per order line, for the shop's books and its stock — what
     * sold, when, at what price. The same orders and filters as the list.
     *
     * @param  array{status?: ?string, q?: ?string, from?: ?string, to?: ?string}  $filters
     * @return list<array<string, mixed>>
     */
    public function lines(VendorScope $scope, array $filters = [], int $limit = 5000): array
    {
        $out = [];
        foreach ($this->execute($scope, $filters, $limit)['orders'] as $order) {
            foreach ($order['items'] as $item) {
                $out[] = [
                    'number' => $order['number'], 'status' => $order['status'], 'placed_at' => $order['placed_at'], 'paid_at' => $order['paid_at'],
                    'sku' => $item['sku'], 'title' => $item['title'], 'variant' => $item['variant'], 'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'], 'line_total' => $item['line_total'], 'currency' => $order['currency'],
                    'island' => $order['address']['island'] ?? '', 'atoll' => $order['address']['atoll'] ?? '',
                ];
            }
        }

        return $out;
    }

    /**
     * One order, for the packing slip and label. Null for another shop's.
     *
     * @return array<string, mixed>|null
     */
    public function one(VendorScope $scope, int $orderId): ?array
    {
        $order = Order::query()->where('vendor_id', $scope->vendorId)->whereKey($orderId)
            ->with(['items', 'events', 'returns.item', 'refunds', 'vendor', 'checkout.orders', 'checkout.slips'])->first();
        if ($order === null) {
            return null;
        }
        $userModel = config('auth.providers.users.model');

        return $this->row($order, $userModel::query()->whereKey($order->user_id)->value('name')) + [
            'vendor' => ['name' => $order->vendor->name, 'legal_name' => $order->vendor->legal_name, 'phone' => $order->vendor->contact_phone, 'address' => $order->vendor->address],
            'checkout_number' => $order->checkout->number,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Order $order, ?string $customer): array
    {
        $visible = OrderView::contactVisible($order);
        $slip = $order->checkout->slips->first(fn (BankTransferSlip $s) => $s->status === SlipStatus::Waiting);
        $onlyShop = $order->checkout->orders->count() === 1;

        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status->value,
            'collection' => $order->isCollection(),
            'customer' => $customer,
            'address' => $visible ? ($order->address_snapshot ?? []) + ['masked' => false] : OrderView::maskAddress($order->address_snapshot),
            'delivery' => ['kind' => $order->delivery_kind->value, 'name' => $order->delivery_name, 'fee' => (string) $order->delivery_fee, 'carrier_paid' => (bool) $order->delivery_carrier_paid, 'handling_days' => (int) $order->delivery_handling_days],
            'items' => $order->items->map(fn (OrderItem $i) => ['id' => $i->id, 'title' => $i->title, 'variant' => $i->variant_name, 'sku' => $i->sku, 'quantity' => (int) $i->quantity, 'unit_price' => (string) $i->unit_price, 'line_total' => (string) $i->line_total])->values()->all(),
            'subtotal' => (string) $order->subtotal,
            'discount' => (string) $order->discount,
            'total' => (string) $order->total,
            'currency' => $order->currency,
            'notes' => $order->notes,
            'gift_message' => $order->gift_message,
            'payment_method' => $order->checkout->payment_method->value,
            // B9b: the shop takes the cash when it hands the order over.
            'awaiting_cash' => $order->paid_at === null && $order->checkout->payment_method === \App\Domains\Bookshop\Enums\CheckoutPaymentMethod::CashOnDelivery,
            'carrier' => $order->carrier,
            'tracking_note' => $order->tracking_note,
            'cancel_reason' => $order->cancel_reason,
            'placed_at' => $order->created_at?->toDateTimeString(),
            'paid_at' => $order->paid_at?->toDateTimeString(),
            'next' => OrderView::nextSteps($order),
            'cancellable' => $order->status->cancellableByVendor(),
            'events' => $order->events->map(fn (OrderEvent $e) => ['type' => $e->type, 'note' => $e->note, 'at' => $e->created_at?->toDateTimeString()])->values()->all(),
            'returns' => $order->returns->map(fn (OrderReturn $r) => OrderView::returnRow($r))->values()->all(),
            'refunds' => $order->refunds->map(fn (OrderRefund $r) => OrderView::refund($r))->values()->all(),
            'slip' => $slip === null ? null : ['id' => $slip->id, 'reference' => $slip->reference, 'uploaded_at' => $slip->created_at?->toDateTimeString(), 'can_confirm' => $onlyShop],
            'message_thread_id' => $order->message_thread_id,
        ];
    }
}
