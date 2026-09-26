<?php

namespace App\Domains\Bookshop\Actions\Orders;

use App\Domains\Bookshop\Enums\SlipStatus;
use App\Domains\Bookshop\Models\BankTransferSlip;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Order;

/**
 * A checkout as its customer sees it after placing it (BOOKSHOP_PLAN §4):
 * its state, the orders under it, and — for a bank transfer — the account
 * to pay into and the slips uploaded so far. Null for anyone else's.
 */
class PresentCheckoutAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(int $userId, string $number): ?array
    {
        $checkout = BookshopCheckout::query()->where('user_id', $userId)->where('number', $number)->with(['orders.vendor', 'orders.items', 'slips'])->first();
        if ($checkout === null) {
            return null;
        }

        return [
            'number' => $checkout->number,
            'status' => $checkout->status->value,
            'payment_method' => $checkout->payment_method->value,
            'subtotal' => (string) $checkout->subtotal,
            'discount' => (string) $checkout->discount,
            'delivery_total' => (string) $checkout->delivery_total,
            'total' => (string) $checkout->total,
            'currency' => $checkout->currency,
            'expires_at' => $checkout->expires_at?->toDateTimeString(),
            'paid_at' => $checkout->paid_at?->toDateTimeString(),
            'address' => $checkout->address_snapshot,
            'orders' => $checkout->orders->map(fn (Order $o) => PresentOrderAction::summary($o))->values()->all(),
            'bank' => $checkout->payment_method->value === 'bank_transfer' ? [
                'bank' => (string) config('bookshop.bank_transfer.bank'),
                'account_name' => (string) config('bookshop.bank_transfer.account_name'),
                'account_number' => (string) config('bookshop.bank_transfer.account_number'),
            ] : null,
            'slips' => $checkout->slips->map(fn (BankTransferSlip $s) => [
                'id' => $s->id,
                'status' => $s->status->value,
                'reference' => $s->reference,
                'decision_note' => $s->decision_note,
                'uploaded_at' => $s->created_at?->toDateTimeString(),
            ])->values()->all(),
            'awaiting_slip' => $checkout->payment_method->value === 'bank_transfer'
                && $checkout->status->value === 'pending_payment'
                && ! $checkout->slips->contains(fn (BankTransferSlip $s) => $s->status === SlipStatus::Waiting),
        ];
    }
}
