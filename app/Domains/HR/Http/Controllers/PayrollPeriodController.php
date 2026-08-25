<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\HR\Actions\ApprovePayrollPeriodAction;
use App\Domains\HR\Actions\ExportPayrollBankCsvAction;
use App\Domains\HR\Actions\ListPayslipsAction;
use App\Domains\HR\Actions\LockPayrollPeriodAction;
use App\Domains\HR\Actions\MarkPayrollPaidAction;
use App\Domains\HR\Actions\ResolvePayrollSettingsAction;
use App\Domains\HR\Actions\RunPayrollAction;
use App\Domains\HR\Models\PayrollPeriod;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollPeriodController extends Controller
{
    public function index(Request $request): Response
    {
        $this->guard($request, 'payroll.run');

        $periodId = $request->integer('period_id') ?: PayrollPeriod::query()->orderByDesc('year')->orderByDesc('month')->value('id');

        return Inertia::render('HR/Payroll/Index', [
            'enabled' => app(ResolvePayrollSettingsAction::class)->execute()['enabled'],
            'periods' => PayrollPeriod::query()->orderByDesc('year')->orderByDesc('month')->get()
                ->map(fn (PayrollPeriod $period) => [
                    'id' => $period->id,
                    'year' => $period->year,
                    'month' => $period->month,
                    'status' => $period->status?->value ?? $period->status,
                ])->values(),
            'periodId' => $periodId,
            'rows' => $periodId ? app(ListPayslipsAction::class)->execute((int) $periodId)->values() : collect(),
            'canApprove' => (bool) $request->user()?->can('payroll.approve'),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $this->guard($request, 'payroll.run');

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $period = app(RunPayrollAction::class)->execute((int) $data['year'], (int) $data['month'], (int) $request->user()->id);

        return redirect()
            ->route('hr.payroll.index', ['period_id' => $period->id])
            ->with('success', 'Draft payslips generated.');
    }

    public function approve(Request $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $this->guard($request, 'payroll.approve');

        app(ApprovePayrollPeriodAction::class)->execute((int) $payrollPeriod->id, (int) $request->user()->id);

        return redirect()->route('hr.payroll.index', ['period_id' => $payrollPeriod->id])->with('success', 'Period approved.');
    }

    public function pay(Request $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $this->guard($request, 'payroll.approve');

        app(MarkPayrollPaidAction::class)->execute((int) $payrollPeriod->id);

        return redirect()->route('hr.payroll.index', ['period_id' => $payrollPeriod->id])->with('success', 'Period marked paid.');
    }

    public function lock(Request $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        $this->guard($request, 'payroll.approve');

        app(LockPayrollPeriodAction::class)->execute((int) $payrollPeriod->id);

        return redirect()->route('hr.payroll.index', ['period_id' => $payrollPeriod->id])->with('success', 'Period locked.');
    }

    public function export(Request $request, PayrollPeriod $payrollPeriod): StreamedResponse
    {
        $this->guard($request, 'payroll.run');

        $csv = app(ExportPayrollBankCsvAction::class)->execute((int) $payrollPeriod->id);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, 'payroll-bank-'.$payrollPeriod->year.'-'.$payrollPeriod->month.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function guard(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission), 403);
        abort_unless(app(ResolvePayrollSettingsAction::class)->execute()['enabled'], 403, 'Payroll is disabled.');
    }
}
