<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentService;
use Illuminate\Validation\ValidationException;

/**
 * Generic BML initiation for ANY payable (morph alias + id): creates the
 * Payment row and asks the provider for the redirect URL. Finance stays
 * the only owner of the payment flow; callers pass a payable they own.
 * Access to whatever was bought is granted on webhook confirmation
 * (PaymentConfirmed), never here and never on the return URL.
 */
class InitiatePayablePaymentAction
{
    /**
     * @return array{payment: Payment, redirect_url: ?string, error: ?string}
     */
    public function execute(
        string $payableType,
        int $payableId,
        int $userId,
        float $amount,
        string $currency = 'MVR',
        ?string $returnUrl = null,
    ): array {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be positive.']);
        }

        $payment = Payment::query()->create([
            'user_id' => $userId,
            'amount' => $amount,
            'currency' => $currency ?: 'MVR',
            'status' => 'initiated',
            'provider' => 'bml',
            'payable_type' => $payableType,
            'payable_id' => $payableId,
        ]);

        $result = app(PaymentService::class)->initiatePayment($payment, [
            'return_url' => ($returnUrl ?? route('payments.bml.return'))
                .(str_contains((string) ($returnUrl ?? ''), '?') ? '&' : '?')
                .'ref='.$payment->merchant_reference,
        ]);

        return [
            'payment' => $payment->refresh(),
            'redirect_url' => $result->success ? $result->redirectUrl : null,
            'error' => $result->success ? null : ($result->error ?? 'Payment initiation failed.'),
        ];
    }
}
