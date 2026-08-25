<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\InstallmentStatus;
use App\Domains\Finance\Enums\PaymentPlanStatus;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\PaymentPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePaymentPlanAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): PaymentPlan
    {
        $invoice = Invoice::query()->find((int) ($data['invoice_id'] ?? 0));
        if ($invoice === null) {
            throw ValidationException::withMessages(['invoice_id' => 'Invoice not found.']);
        }
        if ($invoice->payment_plan_id) {
            throw ValidationException::withMessages(['invoice_id' => 'Invoice already has a payment plan.']);
        }

        $balance = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
        if ($balance <= 0) {
            throw ValidationException::withMessages(['invoice_id' => 'Invoice has no remaining balance.']);
        }

        $rows = $data['installments'] ?? [];
        if (! is_array($rows) || $rows === []) {
            throw ValidationException::withMessages(['installments' => 'Add at least one installment.']);
        }

        $sum = 0.0;
        $normalized = [];
        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['installments' => 'Each installment must be greater than zero.']);
            }
            $due = (string) ($row['due_date'] ?? '');
            if ($due === '') {
                throw ValidationException::withMessages(['due_date' => 'Each installment needs a due date.']);
            }
            $sum += $amount;
            $normalized[] = [
                'sequence' => $index + 1,
                'due_date' => $due,
                'amount' => $amount,
                'paid_amount' => 0,
                'status' => InstallmentStatus::Pending,
            ];
        }

        if (abs($sum - $balance) > 0.009) {
            throw ValidationException::withMessages([
                'installments' => 'Installments must sum to the invoice balance ('.$balance.').',
            ]);
        }

        $createdBy = (int) ($data['created_by'] ?? 0);
        if ($createdBy < 1) {
            throw ValidationException::withMessages(['created_by' => 'Created by is required.']);
        }

        return DB::transaction(function () use ($invoice, $balance, $normalized, $createdBy, $data) {
            $plan = PaymentPlan::query()->create([
                'invoice_id' => $invoice->id,
                'total_amount' => $balance,
                'status' => PaymentPlanStatus::Active,
                'created_by' => $createdBy,
                'approved_by' => isset($data['approved_by']) ? (int) $data['approved_by'] : $createdBy,
            ]);
            foreach ($normalized as $row) {
                $plan->installments()->create($row);
            }
            $invoice->payment_plan_id = $plan->id;
            $invoice->save();

            return $plan->fresh('installments');
        });
    }
}
