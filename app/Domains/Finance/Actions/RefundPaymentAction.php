<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Finance\Events\PaymentRefunded;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\PaymentRefund;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P4.3: the ONLY way a payment is refunded. Locks the payment, enforces
 * the refundable remainder (partials allowed, never over the amount
 * paid), appends the refund row, credits the wallet when that is the
 * destination ("manual" records a money return the operator makes
 * outside the system, e.g. a BML transfer), flips the payment to
 * refunded once fully refunded, and fires PaymentRefunded inside the
 * transaction so listeners revoke what the money bought.
 */
class RefundPaymentAction
{
    public function execute(
        int $paymentId,
        float $amount,
        string $destination,
        ?int $refundedByUserId = null,
        ?string $reason = null,
    ): PaymentRefund {
        if (! in_array($destination, ['wallet', 'manual'], true)) {
            throw ValidationException::withMessages(['destination' => 'Refund destination must be wallet or manual.']);
        }
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Refund amount must be positive.']);
        }

        return DB::transaction(function () use ($paymentId, $amount, $destination, $refundedByUserId, $reason) {
            $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->firstOrFail();

            if (! in_array($payment->status, ['confirmed', 'paid'], true)) {
                throw ValidationException::withMessages(['payment' => 'Only confirmed payments can be refunded.']);
            }

            $alreadyRefunded = (float) PaymentRefund::query()
                ->where('payment_id', $payment->id)
                ->sum('amount');
            $refundable = round((float) $payment->amount - $alreadyRefunded, 2);
            if ($amount > $refundable) {
                throw ValidationException::withMessages([
                    'amount' => "Refund exceeds the refundable remainder ({$refundable}).",
                ]);
            }

            if ($destination === 'wallet' && $payment->user_id === null) {
                throw ValidationException::withMessages(['destination' => 'This payment has no payer account to credit.']);
            }

            $refund = PaymentRefund::query()->create([
                'payment_id' => $payment->id,
                'amount' => $amount,
                'currency' => $payment->currency ?? 'MVR',
                'destination' => $destination,
                'reason' => $reason,
                'refunded_by_user_id' => $refundedByUserId,
            ]);

            if ($destination === 'wallet') {
                app(CreditWalletAction::class)->execute(
                    (int) $payment->user_id,
                    $amount,
                    'refund',
                    $refund->id,
                    'Refund for '.($payment->local_id ?? $payment->merchant_reference),
                );
            }

            $fullyRefunded = round($alreadyRefunded + $amount, 2) >= round((float) $payment->amount, 2);
            if ($fullyRefunded) {
                $payment->update(['status' => 'refunded']);
            }

            event(new PaymentRefunded($payment->fresh(), $refund, $fullyRefunded));

            return $refund;
        });
    }
}
