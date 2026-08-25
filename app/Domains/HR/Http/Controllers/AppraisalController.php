<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\Academics\Actions\ListAcademicYearsAction;
use App\Domains\HR\Actions\ListAppraisalsAction;
use App\Domains\HR\Actions\SaveAppraisalAction;
use App\Domains\HR\Actions\SaveAppraisalCycleAction;
use App\Domains\HR\Enums\AppraisalStatus;
use App\Domains\People\Actions\ListStaffProfilesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppraisalController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $list = app(ListAppraisalsAction::class)->execute();

        return Inertia::render('HR/Performance/Appraisals', [
            'years' => app(ListAcademicYearsAction::class)->execute()->values(),
            'staff' => app(ListStaffProfilesAction::class)->execute(['status' => 'active'])->values(),
            'statuses' => array_map(fn (AppraisalStatus $status) => $status->value, AppraisalStatus::cases()),
            'cycles' => $list['cycles'],
            'rows' => $list['rows'],
        ]);
    }

    public function storeCycle(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        app(SaveAppraisalCycleAction::class)->execute($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date'],
        ]));

        return redirect()->route('hr.appraisals.index')->with('success', 'Appraisal cycle opened.');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        app(SaveAppraisalAction::class)->execute($request->validate([
            'cycle_id' => ['required', 'integer', 'exists:appraisal_cycles,id'],
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'strengths' => ['nullable', 'string'],
            'development_areas' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(AppraisalStatus::class)],
        ]) + ['appraiser_id' => $request->user()?->id]);

        return redirect()->route('hr.appraisals.index')->with('success', 'Appraisal saved.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $rows = app(ListAppraisalsAction::class)->execute()['rows'];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['staff_name', 'cycle_name', 'status']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['staff_name'], $row['cycle_name'], $row['status']]);
            }
            fclose($out);
        }, 'appraisals.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
