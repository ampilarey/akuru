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
     * `$context` carries SPEC §38's payment fields that only the caller knows:
     * `student_id`, `unified_student_id`, `course_id`, `course_offering_id`,
     * `metadata`.
     *
     * This row used to carry the payer, the amount and the payable and nothing
     * else — `student_id`, `course_id` and (once it existed)
     * `course_offering_id` were left null on every engine payment, even though
     * the columns were there and the legacy public checkout filled them. That
     * was not only a missing §38 field: `PaymentService::
     * recordPaymentCompletedFunnel()` resolves the course from
     * `payment->course_id` or from `payment_items`, and an engine payment has
     * neither — so **every course bought through the engine recorded no
     * `payment_completed` funnel event at all**, and the conversion reporting
     * silently under-counted them.
     *
     * `payment_method` is not taken here on purpose. BML Connect does not
     * report an instrument back, so a gateway payment leaves it null, which
     * reads as "whatever the gateway processed" rather than as a guess
     * recorded as fact.
     *
     * @param  array<string, mixed>  $context
     * @return array{payment: Payment, redirect_url: ?string, error: ?string}
     */
    public function execute(
        string $payableType,
        int $payableId,
        int $userId,
        float $amount,
        string $currency = 'MVR',
        ?string $returnUrl = null,
        array $context = [],
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
        ] + $this->contextColumns($context));

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

    /**
     * Only the §38 context keys, and only the ones actually supplied — an
     * absent key must stay null rather than become 0.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function contextColumns(array $context): array
    {
        $out = [];

        foreach (['student_id', 'unified_student_id', 'course_id', 'course_offering_id'] as $key) {
            $id = $context[$key] ?? null;
            if ($id !== null && $id !== '' && (int) $id > 0) {
                $out[$key] = (int) $id;
            }
        }

        if (is_array($context['metadata'] ?? null) && $context['metadata'] !== []) {
            $out['metadata'] = $context['metadata'];
        }

        return $out;
    }
}
