<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Enums\RefundStatus;
use App\Domains\Bookshop\Models\OrderRefund;

/**
 * Card refunds waiting for the office (plan audit finding 6: a shop
 * accepts, the office returns the money through BML and records it here),
 * then the latest done ones. People named through the auth model (rule 3).
 *
 * @return list<array<string, mixed>>
 */
class ListPendingRefundsAction
{
    public function execute(int $limit = 100): array
    {
        $refunds = OrderRefund::query()->with('order.vendor', 'order.checkout')
            ->orderByRaw('case when status = ? then 0 else 1 end', [RefundStatus::Pending->value])
            ->orderByDesc('id')->limit($limit)->get();

        $userModel = config('auth.providers.users.model');
        $people = $userModel::query()->whereIn('id', $refunds->pluck('order.user_id')->unique()->all())->get(['id', 'name', 'email'])->keyBy('id');

        return $refunds->map(fn (OrderRefund $r) => [
            'id' => $r->id,
            'status' => $r->status->value,
            'order_number' => $r->order->number,
            'vendor' => $r->order->vendor->name,
            'customer' => $people->get($r->order->user_id)?->name ?? ('#'.$r->order->user_id),
            'customer_email' => $people->get($r->order->user_id)?->email,
            'amount' => (string) $r->amount,
            'currency' => $r->currency,
            'paid_with' => $r->paid_with,
            'destination' => $r->destination,
            'reason' => $r->reason,
            'bml_reference' => $r->order->checkout->payment_id,
            'requested_at' => $r->created_at?->toDateTimeString(),
            'processed_at' => $r->processed_at?->toDateTimeString(),
        ])->values()->all();
    }
}
