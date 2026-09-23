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
        app(ResolvePayrollSettingsAction::class)->assertEnabled();

        $period = PayrollPeriod::query()->findOrFail($periodId);

        if (! in_array($period->status, [PayrollPeriodStatus::Approved, PayrollPeriodStatus::Paid], true)) {
            throw ValidationException::withMessages(['payroll' => 'Approve the period before marking it paid.']);
        }

        return DB::transaction(function () use ($period): PayrollPeriod {
            $payslips = Payslip::query()
                ->where('payroll_period_id', $period->id)
                ->where('status', PayslipStatus::Final)
                ->get();

            // Trilingual per S5.6 ("payslip PDF (trilingual)"). Before this
            // there was no documents/payslip view, so every payslip fell
            // through to the renderer's generic key/value fallback — hardcoded
            // lang="en", column names as labels — and was stored as a
            // `receipt`, the nearest type the enum had (S5 audit D2, §5fe).
            // Names by DB query, not the People model (rule 3).
            $names = DB::table('staff_profiles')
                ->whereIn('id', $payslips->pluck('staff_profile_id'))
                ->get(['id', 'first_name', 'last_name'])
                ->keyBy('id');
            $locale = app()->getLocale();
            $label = $period->year.'-'.str_pad((string) $period->month, 2, '0', STR_PAD_LEFT);

            $renderer = app(DocumentRendererInterface::class);
            foreach ($payslips as $payslip) {
                if ($payslip->document_id) {
                    continue;
                }

                $profile = $names->get($payslip->staff_profile_id);
                $html = $renderer->render('payslip', [
                    'title' => 'Payslip '.$label,
                    'staff_name' => $profile ? trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')) : '',
                    'period' => $label,
                    'basic_salary' => number_format((float) $payslip->basic_salary, 2, '.', ''),
                    'gross' => number_format((float) $payslip->gross, 2, '.', ''),
                    'net_pay' => number_format((float) $payslip->net_pay, 2, '.', ''),
                    'employee_pension' => number_format((float) $payslip->employee_pension, 2, '.', ''),
                    'employer_pension' => number_format((float) $payslip->employer_pension, 2, '.', ''),
                    'tax_withheld' => number_format((float) $payslip->tax_withheld, 2, '.', ''),
                    'unpaid_leave_deduction' => number_format((float) $payslip->unpaid_leave_deduction, 2, '.', ''),
                    'locale' => $locale,
                    'dir' => in_array($locale, ['dv', 'ar'], true) ? 'rtl' : 'ltr',
                ]);

                $document = app(StoreRenderedDocumentAction::class)->execute(
                    'payslip',
                    (int) $payslip->id,
                    'Payslip '.$label,
                    $html,
                    $period->approved_by,
                    'payslip',
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
