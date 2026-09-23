<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\HR\Actions\ResolveHrChecklistSettingsAction;
use App\Domains\HR\Actions\ResolveHrSettingsAction;
use App\Domains\HR\Actions\ResolvePayrollSettingsAction;
use App\Domains\HR\Actions\SaveHrSettingsAction;
use App\Domains\HR\Actions\SavePayrollSettingsAction;
use App\Domains\Settings\Contracts\SettingsRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Thin (rule 5): every bound lives in the two Actions. */
class HrSettingsController extends Controller
{
    public function index(Request $request, SettingsRepositoryInterface $settings): Response
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $payroll = app(ResolvePayrollSettingsAction::class)->execute();

        return Inertia::render('HR/Settings/Index', [
            'hr' => [
                ...app(ResolveHrSettingsAction::class)->execute(),
                ...app(ResolveHrChecklistSettingsAction::class)->execute(),
            ],
            'payroll' => [
                'rules' => $payroll['rules'],
                'setting_on' => filter_var($settings->get('payroll.enabled') ?? false, FILTER_VALIDATE_BOOLEAN),
                'environment_on' => (bool) config('payroll.enabled'),
                'enabled' => $payroll['enabled'],
            ],
            'canApprovePayroll' => (bool) $request->user()?->can('payroll.approve'),
        ]);
    }

    public function update(Request $request, SaveHrSettingsAction $save): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $save->execute($request->all());

        return back()->with('success', 'HR settings saved. The portal and every new checklist use them from now on.');
    }

    public function updatePayroll(Request $request, SavePayrollSettingsAction $save): RedirectResponse
    {
        abort_unless($request->user()?->can('payroll.approve'), 403);

        $save->execute($request->all());

        return back()->with('success', 'Payroll settings saved. The next run computes with them.');
    }
}
