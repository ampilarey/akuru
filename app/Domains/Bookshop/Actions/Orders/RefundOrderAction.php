<?php

namespace App\Domains\Bookshop\Actions\Orders;

use App\Domains\Bookshop\Actions\Money\ReverseVendorEarningAction;
use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Enums\CheckoutPaymentMethod;
use App\Domains\Bookshop\Enums\RefundStatus;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderRefund;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Finance\Actions\RefundPaymentAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Money going back for an order (BOOKSHOP_PLAN §4 "Refunds", audit
 * finding 6). One path for a cancellation and an accepted return:
 *
 *  - paid from the **wallet**: credited back to the wallet at once, a
 *    reversal on Commerce's append-only ledger (rule 12);
 *  - paid by **bank transfer**: back to the wallet at once, through
 *    Finance's `RefundPaymentAction` against the payment the office's
 *    confirmation recorded;
 *  - paid by **card**: waits for the office, who returns it through BML's
 *    merchant portal and records it here (`process`), or credits the
 *    wallet instead where the customer asks; `RefundPaymentAction` either
 *    way, so Finance's books carry every card refund;
 *  - a checkout a discount made free: nothing to send back.
 *
 * Never more than the order's total less what has already gone back.
 */
class RefundOrderAction
{
    public function request(Order $order, float $amount, string $reason, ?int $byUserId, ?int $returnId = null): ?OrderRefund
    {
        $amount = round($amount, 2);
        $checkout = $order->checkout;
        $paidWith = $checkout->payment_method;
        if ($amount <= 0 || $paidWith === CheckoutPaymentMethod::None) {
            return null;
        }
        // B9b: a cash order not yet handed over has taken no money — nothing goes back.
        if ($paidWith === CheckoutPaymentMethod::CashOnDelivery && $order->paid_at === null) {
            return null;
        }

        $refund = DB::transaction(function () use ($order, $amount, $reason, $byUserId, $returnId, $paidWith) {
            Order::query()->whereKey($order->id)->lockForUpdate()->first();
            $already = (float) OrderRefund::query()->where('order_id', $order->id)->sum('amount');
            $left = round((float) $order->total - $already, 2);
            if ($amount > $left) {
                throw ValidationException::withMessages(['amount' => __('shop.error_refund_too_much', ['left' => number_format(max(0, $left), 2)])]);
            }

            $refund = OrderRefund::query()->create([
                'order_id' => $order->id,
                'order_return_id' => $returnId,
                'amount' => $amount,
                'currency' => $order->currency,
                'paid_with' => $paidWith->value,
                'status' => RefundStatus::Pending->value,
                'reason' => $reason,
                'requested_by' => $byUserId,
            ]);

            // B6: the vendor's earning reverses in proportion, the moment the money is owed back.
            app(ReverseVendorEarningAction::class)->execute($order, $amount);

            // B9b: cash went to the shop; Akuru refunds to the wallet at once and the shop's earning owes it back.
            if ($paidWith === CheckoutPaymentMethod::Wallet || $paidWith === CheckoutPaymentMethod::CashOnDelivery) {
                app(CreditWalletAction::class)->execute((int) $order->user_id, $amount, 'bookshop_refund', $refund->id, 'Refund: Akuru Bookstore '.$order->number);
                $refund->update(['status' => RefundStatus::Done->value, 'destination' => 'wallet', 'processed_by' => $byUserId, 'processed_at' => now()]);
            } elseif ($paidWith === CheckoutPaymentMethod::BankTransfer) {
                $this->toWallet($refund, $order, $byUserId);
            }

            return $refund;
        });

        $this->tell($refund->refresh(), $order);

        return $refund;
    }

    /**
     * The office returns card money (or credits the wallet instead) and
     * records it. `manual` is Finance's word for money returned outside the
     * system — here, through BML's merchant portal.
     */
    public function process(int $refundId, string $destination, int $officeUserId, ?string $note = null): OrderRefund
    {
        if (! in_array($destination, ['manual', 'wallet'], true)) {
            throw ValidationException::withMessages(['destination' => __('shop.error_refund_destination')]);
        }

        $refund = DB::transaction(function () use ($refundId, $destination, $officeUserId, $note) {
            $refund = OrderRefund::query()->whereKey($refundId)->lockForUpdate()->firstOrFail();
            if ($refund->status !== RefundStatus::Pending) {
                throw ValidationException::withMessages(['refund' => __('shop.error_refund_done')]);
            }
            $order = $refund->order()->with('checkout')->firstOrFail();
            $paymentId = $order->checkout->payment_id;
            if ($paymentId === null) {
                throw ValidationException::withMessages(['refund' => __('shop.error_refund_no_payment')]);
            }

            $financeRefund = app(RefundPaymentAction::class)->execute((int) $paymentId, (float) $refund->amount, $destination, $officeUserId, 'Akuru Bookstore '.$order->number.': '.$refund->reason);
            $refund->update([
                'status' => RefundStatus::Done->value,
                'destination' => $destination === 'manual' ? 'card' : 'wallet',
                'payment_refund_id' => $financeRefund->id,
                'processed_by' => $officeUserId,
                'processed_at' => now(),
                'note' => trim((string) $note) ?: null,
            ]);

            return $refund;
        });

        $this->tell($refund->refresh(), $refund->order);

        return $refund;
    }

    /** Bank-transfer money goes back to the wallet by default (audit finding 6). */
    private function toWallet(OrderRefund $refund, Order $order, ?int $byUserId): void
    {
        $paymentId = $order->checkout->payment_id;
        if ($paymentId !== null) {
            $financeRefund = app(RefundPaymentAction::class)->execute((int) $paymentId, (float) $refund->amount, 'wallet', $byUserId, 'Akuru Bookstore '.$order->number.': '.$refund->reason);
            $refund->payment_refund_id = $financeRefund->id;
        } else {
            // A transfer confirmed before B3 recorded no Finance payment.
            app(CreditWalletAction::class)->execute((int) $order->user_id, (float) $refund->amount, 'bookshop_refund', $refund->id, 'Refund: Akuru Bookstore '.$order->number);
        }
        $refund->fill(['status' => RefundStatus::Done->value, 'destination' => 'wallet', 'processed_by' => $byUserId, 'processed_at' => now()])->save();
    }

    private function tell(OrderRefund $refund, Order $order): void
    {
        $notify = app(NotifyBookshopUserAction::class);
        $amount = $order->currency.' '.number_format((float) $refund->amount, 2);
        if ($refund->status === RefundStatus::Done) {
            $notify->execute(
                (int) $order->user_id,
                __('shop.notice_refund_done_title'),
                __($refund->destination === 'card' ? 'shop.notice_refund_card_body' : 'shop.notice_refund_wallet_body', ['amount' => $amount, 'number' => $order->number]),
                '/my-orders/'.$order->number,
                'refund',
            );

            return;
        }
        $notify->office(__('shop.notice_refund_pending_title'), __('shop.notice_refund_pending_body', ['amount' => $amount, 'number' => $order->number]), '/admin/bookshop');
    }
}
