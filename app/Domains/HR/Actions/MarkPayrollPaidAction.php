<?php

namespace App\Domains\HR\Actions;

use App\Domains\Finance\Actions\RecordPayrollPostingAction;
use App\Domains\HR\Enums\PayrollPeriodStatus;
use App\Domains\HR\Enums\PayslipStatus;
use App\Domains\HR\Models\PayrollPeriod;
use App\Domains\HR\Models\Payslip;
use App\Domains\Media\Actions\StoreRenderedDocumentAction;
use App\Support\Contracts\DocumentRendererInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkPayrollPaidAction
{
    public function execute(int $periodId): PayrollPeriod
    {
        $period = PayrollPeriod::query()->findOrFail($periodId);

        if (! in_array($period->status, [PayrollPeriodStatus::Approved, PayrollPeriodStatus::Paid], true)) {
            throw ValidationException::withMessages(['payroll' => 'Approve the period before marking it paid.']);
        }

        return DB::transaction(function () use ($period): PayrollPeriod {
            $payslips = Payslip::query()
                ->where('payroll_period_id', $period->id)
                ->where('status', PayslipStatus::Final)
                ->get();

            $renderer = app(DocumentRendererInterface::class);
            foreach ($payslips as $payslip) {
                if ($payslip->document_id) {
                    continue;
                }

                $html = $renderer->render('payslip', [
                    'title' => 'Payslip '.$period->year.'-'.str_pad((string) $period->month, 2, '0', STR_PAD_LEFT),
                    'gross' => $payslip->gross,
                    'net_pay' => $payslip->net_pay,
                    'employee_pension' => $payslip->employee_pension,
                    'tax_withheld' => $payslip->tax_withheld,
                    'unpaid_leave_deduction' => $payslip->unpaid_leave_deduction,
                ]);

                $document = app(StoreRenderedDocumentAction::class)->execute(
                    'payslip',
                    (int) $payslip->id,
                    'Payslip',
                    $html,
                    $period->approved_by,
                    'receipt',
                );
                $payslip->document_id = $document['id'];
                $payslip->save();
            }

            app(RecordPayrollPostingAction::class)->execute(
                (int) $period->year,
                (int) $period->month,
                (float) $payslips->sum('net_pay'),
                $payslips->count(),
            );

            $period->status = PayrollPeriodStatus::Paid;
            $period->paid_at = now();
            $period->save();

            return $period->refresh();
        });
    }
}
