<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\InstallmentStatus;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\PaymentPlan;
use Illuminate\Validation\ValidationException;

class InitiateInvoicePaymentAction
{
    /**
     * @return array{payment: Payment, amount: float}
     */
    public function execute(Invoice $invoice, int $userId, string $mode = 'full'): array
    {
        $balance = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
        if ($balance <= 0) {
            throw ValidationException::withMessages(['invoice_id' => 'Invoice is already paid.']);
        }

        $amount = $balance;
        if ($mode === 'installment') {
            $plan = PaymentPlan::query()->where('invoice_id', $invoice->id)->first();
            $next = $plan?->installments()
                ->whereIn('status', [
                    InstallmentStatus::Pending->value,
                    InstallmentStatus::Partial->value,
                    InstallmentStatus::Overdue->value,
                ])
                ->orderBy('sequence')
                ->first();
            if ($next) {
                $amount = $next->remaining();
            }
        }

        $payment = Payment::query()->create([
            'user_id' => $userId,
            'unified_student_id' => $invoice->student_id,
            'amount' => $amount,
            'currency' => $invoice->currency ?: 'MVR',
            'status' => 'initiated',
            'provider' => 'bml',
            'payable_type' => 'invoice',
            'payable_id' => $invoice->id,
        ]);

        return ['payment' => $payment, 'amount' => $amount];
    }
}
