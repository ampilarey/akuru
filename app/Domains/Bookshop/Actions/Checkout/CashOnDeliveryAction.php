<?php

namespace App\Domains\Bookshop\Actions\Checkout;

use App\Domains\Bookshop\Actions\Money\RecordVendorEarningAction;
use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Enums\CheckoutPaymentMethod;
use App\Domains\Bookshop\Enums\CheckoutStatus;
use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderEvent;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorEarning;
use App\Domains\Commerce\Actions\RecordDiscountRedemptionAction;
use App\Domains\Settings\Actions\SetSettingAction;
use App\Domains\Settings\Contracts\SettingsRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cash on delivery (BOOKSHOP_PLAN decision 7, slice B9b).
 *
 * Who may use it: the office's switch is on, every shop in the basket has
 * opted in, each chose a delivery the shop hands over itself (its own
 * collection point or couriers — never a boat, never the Akuru counter),
 * and no shop's order is over the shop's own cap.
 *
 * How it runs: placing the order takes the stock at once (as payment
 * would) and never expires; the shop prepares it as usual; marking it
 * delivered or collected **with the cash received** is the moment it is
 * paid — the sale counts, the earning is recorded with the cash the shop
 * now holds (so the shop owes Akuru the commission, which the next payout
 * settles), and the checkout closes once all its orders are paid or
 * cancelled. A cash order cancelled before delivery has nothing to refund.
 */
class CashOnDeliveryAction
{
    public function isOn(): bool
    {
        $value = app(SettingsRepositoryInterface::class)->get((string) config('bookshop.cod.setting_key'));
        if ($value === null || $value === '') {
            return (bool) config('bookshop.cod.enabled_by_default', true);
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }

    public function setOn(bool $on): void
    {
        app(SetSettingAction::class)->execute((string) config('bookshop.cod.setting_key'), $on, 'boolean', 'bookshop', 'Bookstore: cash on delivery');
    }

    /** Offered at all for this basket: the switch, and every shop opted in. */
    public function offeredFor(iterable $vendors): bool
    {
        if (! $this->isOn()) {
            return false;
        }
        $any = false;
        foreach ($vendors as $vendor) {
            /** @var Vendor $vendor */
            $any = true;
            if (! $vendor->cod_enabled) {
                return false;
            }
        }

        return $any;
    }

    /** Why this shop's order cannot be paid in cash, or null when it can. */
    public function blocker(Vendor $vendor, string $deliveryKind, float $orderTotal): ?string
    {
        if (! $this->isOn()) {
            return __('shop.error_cod_off');
        }
        if (! $vendor->cod_enabled) {
            return __('shop.error_cod_vendor', ['vendor' => $vendor->name]);
        }
        if (! in_array($deliveryKind, (array) config('bookshop.cod.delivery_kinds'), true)) {
            return __('shop.error_cod_delivery', ['vendor' => $vendor->name]);
        }
        if ($vendor->cod_max !== null && $orderTotal > (float) $vendor->cod_max) {
            return __('shop.error_cod_max', ['vendor' => $vendor->name, 'max' => number_format((float) $vendor->cod_max, 2)]);
        }

        return null;
    }

    /**
     * Just placed: take the stock, confirm the discount, tell everyone.
     * Idempotent by status, like the paid path.
     */
    public function place(int $checkoutId): ?BookshopCheckout
    {
        $placed = DB::transaction(function () use ($checkoutId) {
            $checkout = BookshopCheckout::query()->whereKey($checkoutId)->lockForUpdate()->first();
            if ($checkout === null || $checkout->status !== CheckoutStatus::PendingPayment || $checkout->payment_method !== CheckoutPaymentMethod::CashOnDelivery) {
                return null;
            }
            $checkout->update(['status' => CheckoutStatus::CashOnDelivery->value]);
            foreach ($checkout->orders as $order) {
                /** @var Order $order */
                $short = app(MarkCheckoutPaidAction::class)->takeStock($order);
                $order->update(['status' => ($short === [] ? OrderStatus::CashDue : OrderStatus::NeedsAttention)->value]);
                if ($short !== []) {
                    OrderEvent::query()->create(['order_id' => $order->id, 'type' => 'needs_attention', 'created_at' => now(), 'note' => 'Stock ran out before the order was placed: '.implode(', ', $short)]);
                }
            }
            $checkout->reservations()->delete();
            app(RecordDiscountRedemptionAction::class)->transition('bookshop_checkout', $checkout->id, 'confirmed');
            app(\App\Domains\Bookshop\Actions\Shop\CustomerQuotesAction::class)->markOrdered((int) $checkout->id);

            return $checkout->refresh();
        });
        if ($placed === null) {
            return null;
        }

        $notify = app(NotifyBookshopUserAction::class);
        $notify->execute((int) $placed->user_id, __('shop.notice_cod_placed_title'), __('shop.notice_cod_placed_body', ['number' => $placed->number, 'amount' => $placed->currency.' '.number_format((float) $placed->total, 2)]), '/my-orders', 'order_paid');
        foreach ($placed->orders as $order) {
            $notify->vendor((int) $order->vendor_id, __('shop.notice_vendor_cod_order_title'), __('shop.notice_vendor_cod_order_body', ['number' => $order->number, 'amount' => $order->currency.' '.number_format((float) $order->total, 2)]), '/vendor/orders', 'new_order');
        }

        return $placed;
    }

    /** Does this order still wait for its cash? */
    public function awaitingCash(Order $order): bool
    {
        return $order->paid_at === null && $order->checkout?->payment_method === CheckoutPaymentMethod::CashOnDelivery;
    }

    /**
     * Delivered or collected with the cash in hand: the order is paid now.
     * Called inside the shop's "delivered" step, in its transaction.
     */
    public function collect(Order $order, int $byUserId): void
    {
        $order->update(['paid_at' => now()]);
        OrderEvent::query()->create(['order_id' => $order->id, 'type' => 'cash_received', 'actor_user_id' => $byUserId, 'created_at' => now(),
            'note' => $order->currency.' '.number_format((float) $order->total, 2)]);

        $earning = app(RecordVendorEarningAction::class)->execute($order->refresh());
        if ($earning instanceof VendorEarning && (float) $earning->cash_collected <= 0) {
            $cash = round((float) $order->total, 2);
            $earning->update(['cash_collected' => $cash, 'net' => round((float) $earning->net - $cash, 2)]);
        }

        $checkout = BookshopCheckout::query()->whereKey($order->bookshop_checkout_id)->lockForUpdate()->first();
        $open = Order::query()->where('bookshop_checkout_id', $order->bookshop_checkout_id)
            ->whereNull('paid_at')->where('status', '!=', OrderStatus::Cancelled->value)->exists();
        if ($checkout !== null && ! $open) {
            $checkout->update(['status' => CheckoutStatus::Paid->value, 'paid_at' => now()]);
        }
    }

    /**
     * The shop must say it has the cash before a cash order is delivered.
     */
    public function requireCash(Order $order, bool $cashReceived): void
    {
        if ($this->awaitingCash($order) && ! $cashReceived) {
            throw ValidationException::withMessages(['cash_received' => __('shop.error_cash_confirm', ['amount' => $order->currency.' '.number_format((float) $order->total, 2)])]);
        }
    }
}
