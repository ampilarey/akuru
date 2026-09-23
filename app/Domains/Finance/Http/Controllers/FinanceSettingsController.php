<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Actions\ResolveFinanceSettingsAction;
use App\Domains\Finance\Actions\SaveFinanceSettingsAction;
use App\Domains\Settings\Contracts\SettingsRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Thin (rule 5): every bound lives in the Action. */
class FinanceSettingsController extends Controller
{
    public function index(Request $request, SettingsRepositoryInterface $settings): Response
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $resolved = app(ResolveFinanceSettingsAction::class)->execute();

        return Inertia::render('Finance/Settings/Index', [
            'settings' => [
                'invoice_monthly_mode' => $resolved['monthly_mode']->value,
                'invoice_reminder_days' => $resolved['reminder_days'],
                'plan_default_days' => max(0, (int) ($settings->get('finance.plan_default_days') ?? 14)),
            ],
        ]);
    }

    public function update(Request $request, SaveFinanceSettingsAction $save): RedirectResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $save->execute($request->all());

        return back()->with('success', 'Finance settings saved. Generation, reminders and defaulting use them from now on.');
    }
}
