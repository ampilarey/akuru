<?php

namespace App\Domains\Finance\Actions;

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
 */
class RecordManualPaymentAction
{
    public function execute(
        string $payableType,
        int $payableId,
        int $payerUserId,
        float $amount,
        ?string $note = null,
        ?int $recordedByUserId = null,
    ): Payment {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be positive.']);
        }

        return DB::transaction(function () use ($payableType, $payableId, $payerUserId, $amount, $note, $recordedByUserId) {
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
                'payable_type' => $payableType,
                'payable_id' => $payableId,
                'confirmed_at' => now(),
                'paid_at' => now(),
                'notes' => $notes,
            ]);

            event(new PaymentConfirmed($payment->fresh()));

            return $payment->fresh();
        });
    }
}
