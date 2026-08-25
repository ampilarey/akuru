<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\Academics\Actions\ListAcademicYearsAction;
use App\Domains\HR\Actions\AdjustLeaveBalanceAction;
use App\Domains\HR\Actions\CarryOverLeaveAction;
use App\Domains\HR\Actions\EnsureLeaveEntitlementAction;
use App\Domains\HR\Actions\ListLeaveBalancesAction;
use App\Domains\HR\Actions\ListLeaveTypesAction;
use App\Domains\People\Actions\ListStaffProfilesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveBalanceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $yearId = $request->integer('academic_year_id') ?: null;

        return Inertia::render('HR/Leave/Balances', [
            'filters' => [
                'academic_year_id' => $yearId,
                'staff_profile_id' => $request->integer('staff_profile_id') ?: null,
            ],
            'years' => app(ListAcademicYearsAction::class)->execute()->values(),
            'staff' => app(ListStaffProfilesAction::class)->execute(['status' => 'active'])->values(),
            'leaveTypes' => app(ListLeaveTypesAction::class)->execute(true)->values(),
            'rows' => app(ListLeaveBalancesAction::class)->execute([
                'academic_year_id' => $yearId,
                'staff_profile_id' => $request->integer('staff_profile_id') ?: null,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $data = $request->validate([
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ]);

        app(EnsureLeaveEntitlementAction::class)->execute(
            (int) $data['staff_profile_id'],
            (int) $data['leave_type_id'],
            (int) $data['academic_year_id'],
        );

        return redirect()
            ->route('hr.leave-balances.index', ['academic_year_id' => $data['academic_year_id']])
            ->with('success', 'Entitlement created.');
    }

    public function adjust(Request $request, int $entitlement): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $data = $request->validate([
            'days' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        app(AdjustLeaveBalanceAction::class)->execute((int) $entitlement, (float) $data['days'], $data['reason']);

        return redirect()->route('hr.leave-balances.index')->with('success', 'Balance adjusted.');
    }

    public function carryOver(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $data = $request->validate([
            'from_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'to_year_id' => ['required', 'integer', 'exists:academic_years,id', 'different:from_year_id'],
        ]);

        $report = app(CarryOverLeaveAction::class)->execute((int) $data['from_year_id'], (int) $data['to_year_id']);

        return redirect()
            ->route('hr.leave-balances.index', ['academic_year_id' => $data['to_year_id']])
            ->with('success', count($report).' entitlements carried over.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $rows = app(ListLeaveBalancesAction::class)->execute([
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
            'staff_profile_id' => $request->integer('staff_profile_id') ?: null,
        ]);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['staff_name', 'leave_type', 'entitled', 'carried', 'adjusted', 'balance']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['staff_name'],
                    $row['leave_type'],
                    $row['entitled_days'],
                    $row['carried_over_days'],
                    $row['adjusted_days'],
                    $row['balance'],
                ]);
            }
            fclose($out);
        }, 'leave-balances.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
