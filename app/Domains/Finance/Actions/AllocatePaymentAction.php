<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\InstallmentStatus;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Enums\PaymentPlanStatus;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\PaymentPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single allocation gateway (ADR-014). Oldest unpaid installment first.
 */
class AllocatePaymentAction
{
    public function execute(Invoice $invoice, float $amount): Invoice
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payment must be greater than zero.']);
        }

        return DB::transaction(function () use ($invoice, $amount) {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw ValidationException::withMessages(['invoice_id' => 'Invoice not found.']);
            }

            $remaining = round((float) $locked->total_amount - (float) $locked->paid_amount, 2);
            if (round($amount, 2) - $remaining > 0.009) {
                throw ValidationException::withMessages(['amount' => 'Overpayment rejected. Remaining balance is '.$remaining.'.']);
            }

            $locked->paid_amount = round((float) $locked->paid_amount + $amount, 2);
            if ((float) $locked->paid_amount + 0.009 >= (float) $locked->total_amount) {
                $locked->status = InvoiceStatus::Paid;
                $locked->paid_at = now('Indian/Maldives');
            }
            $locked->save();

            $plan = PaymentPlan::query()
                ->where('invoice_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if ($plan !== null) {
                $this->allocateToInstallments($plan, $amount);
            }

            return $locked->fresh(['receipts']);
        });
    }

    private function allocateToInstallments(PaymentPlan $plan, float $amount): void
    {
        $left = round($amount, 2);
        $installments = $plan->installments()
            ->whereIn('status', [
                InstallmentStatus::Pending->value,
                InstallmentStatus::Partial->value,
                InstallmentStatus::Overdue->value,
            ])
            ->orderBy('sequence')
            ->lockForUpdate()
            ->get();

        foreach ($installments as $installment) {
            if ($left <= 0) {
                break;
            }
            $need = $installment->remaining();
            if ($need <= 0) {
                continue;
            }
            $apply = min($need, $left);
            $installment->paid_amount = round((float) $installment->paid_amount + $apply, 2);
            $installment->status = $installment->remaining() <= 0.009
                ? InstallmentStatus::Paid
                : InstallmentStatus::Partial;
            $installment->save();
            $left = round($left - $apply, 2);
        }

        $unpaid = $plan->installments()
            ->where('status', '!=', InstallmentStatus::Paid->value)
            ->exists();

        if (! $unpaid) {
            $plan->status = PaymentPlanStatus::Completed;
            $plan->save();
        }
    }
}
