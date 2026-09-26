<?php

namespace App\Domains\Bookshop\Actions\Checkout;

use App\Domains\Bookshop\Enums\CheckoutStatus;
use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Enums\SlipStatus;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\OrderEvent;
use App\Domains\Commerce\Actions\RecordDiscountRedemptionAction;
use Illuminate\Support\Facades\DB;

/**
 * Plan audit finding 2: a checkout not paid in its window lets its stock go.
 * A bank-transfer checkout with a slip waiting is kept — the customer has
 * paid, the office has not looked yet — and its reservations are extended
 * so the goods stay held while it waits.
 */
class ExpireCheckoutsAction
{
    /**
     * @return array{expired: int, extended: int}
     */
    public function execute(): array
    {
        $expired = 0;
        $extended = 0;

        $due = BookshopCheckout::query()
            ->where('status', CheckoutStatus::PendingPayment->value)
            ->where('expires_at', '<=', now())
            ->pluck('id');

        foreach ($due as $id) {
            $outcome = DB::transaction(function () use ($id) {
                $checkout = BookshopCheckout::query()->whereKey($id)->lockForUpdate()->first();
                if ($checkout === null || $checkout->status !== CheckoutStatus::PendingPayment) {
                    return null;
                }

                if ($checkout->slips()->where('status', SlipStatus::Waiting->value)->exists()) {
                    $until = now()->addMinutes((int) config('bookshop.checkout.reservation_minutes', 30));
                    $checkout->update(['expires_at' => $until]);
                    $checkout->reservations()->update(['expires_at' => $until]);

                    return 'extended';
                }

                $checkout->update(['status' => CheckoutStatus::Expired->value]);
                $checkout->reservations()->delete();
                foreach ($checkout->orders as $order) {
                    $order->update(['status' => OrderStatus::Expired->value]);
                    OrderEvent::query()->create(['order_id' => $order->id, 'type' => 'expired', 'created_at' => now(), 'note' => 'Not paid in time.']);
                }
                app(RecordDiscountRedemptionAction::class)->releaseAbandoned('bookshop_checkout', [$checkout->id]);

                return 'expired';
            });

            if ($outcome === 'expired') {
                $expired++;
            } elseif ($outcome === 'extended') {
                $extended++;
            }
        }

        return ['expired' => $expired, 'extended' => $extended];
    }
}
