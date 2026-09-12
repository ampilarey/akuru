<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\PaymentMethod;
use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Finance\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P4.4 (SPEC §49 "Manual payment recording"): an admin asserts money was
 * received OUTSIDE the gateway (cash at the office, bank transfer). The
 * payment is created confirmed with provider "manual" and flows through
 * the SAME PaymentConfirmed listeners as a webhook confirmation — one
 * money→access path for every kind of money. Rule 12 governs gateway
 * money; this is the admin-recorded counterpart the spec mandates.
 *
 * SPEC §38 lists "Payment method" as its own field, separate from Gateway.
 * This is the path where the answer is actually known — an admin taking cash
 * at the office knows it was cash — and it was being thrown away: the method
 * survived only as English prose inside `notes`, prompted by a form
 * placeholder reading "Note (e.g. cash at office)". So "how much cash came
 * through the office this term" could not be answered from the finance data.
 */
class RecordManualPaymentAction
{
    /**
     * @param  array<string, mixed>  $context  §38 fields the caller knows:
     *                                         `student_id`, `unified_student_id`, `course_id`,
     *                                         `course_offering_id`, `metadata`.
     */
    public function execute(
        string $payableType,
        int $payableId,
        int $payerUserId,
        float $amount,
        ?string $note = null,
        ?int $recordedByUserId = null,
        string $method = 'cash',
        array $context = [],
    ): Payment {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be positive.']);
        }

        // The method arrives as a string, not as the enum: callers live in
        // other domains and rule 3 lets them reach Finance only through
        // Actions, never through its enums. Resolving it here keeps the
        // vocabulary inside the domain that owns it.
        $resolved = PaymentMethod::tryFrom($method);
        if ($resolved === null) {
            throw ValidationException::withMessages([
                'payment_method' => 'Unknown payment method.',
            ]);
        }

        // Wallet and gift-card money is spent inside the product and already
        // recorded by the Commerce ledger. An admin asserting one by hand
        // would create a second record of the same money (rule 11).
        if (! in_array($resolved, PaymentMethod::manualCases(), true)) {
            throw ValidationException::withMessages([
                'payment_method' => $resolved->label().' is recorded by the wallet ledger, not by hand.',
            ]);
        }

        return DB::transaction(function () use ($payableType, $payableId, $payerUserId, $amount, $note, $recordedByUserId, $resolved, $context) {
            $notes = trim(
                'Manual payment'
                .($recordedByUserId !== null ? ' recorded by user #'.$recordedByUserId : '')
                .'.'.($note !== null && $note !== '' ? ' '.$note : '')
            );

            $payment = Payment::query()->create([
                'user_id' => $payerUserId,
                'amount' => $amount,
                'amount_laar' => (int) round($amount * 100),
                'currency' => 'MVR',
                'status' => 'confirmed',
                'provider' => 'manual',
                'payment_method' => $resolved,
                'payable_type' => $payableType,
                'payable_id' => $payableId,
                'confirmed_at' => now(),
                'paid_at' => now(),
                'notes' => $notes,
            ] + $this->contextColumns($context));

            event(new PaymentConfirmed($payment->fresh()));

            return $payment->fresh();
        });
    }

    /**
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
