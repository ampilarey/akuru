<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\HR\Actions\ListStaffContractsAction;
use App\Domains\HR\Actions\SaveStaffContractAction;
use App\Domains\HR\Enums\StaffContractStatus;
use App\Domains\HR\Enums\StaffContractType;
use App\Domains\People\Actions\ListStaffProfilesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffContractController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        return Inertia::render('HR/Contracts/Index', [
            'staff' => app(ListStaffProfilesAction::class)->execute(['status' => 'active'])->values(),
            'types' => array_map(fn (StaffContractType $type) => $type->value, StaffContractType::cases()),
            'statuses' => array_map(fn (StaffContractStatus $status) => $status->value, StaffContractStatus::cases()),
            'rows' => app(ListStaffContractsAction::class)->execute([
                'staff_profile_id' => $request->integer('staff_profile_id') ?: null,
                'status' => $request->string('status')->toString() ?: null,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        app(SaveStaffContractAction::class)->execute($request->validate([
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'contract_type' => ['required', Rule::enum(StaffContractType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'probation_until' => ['nullable', 'date'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'working_hours_per_week' => ['nullable', 'integer', 'min:1', 'max:80'],
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'status' => ['nullable', Rule::enum(StaffContractStatus::class)],
        ]));

        return redirect()->route('hr.contracts.index')->with('success', 'Contract saved.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $rows = app(ListStaffContractsAction::class)->execute();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['staff_name', 'type', 'start_date', 'end_date', 'basic_salary', 'status']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['staff_name'],
                    $row['contract_type'],
                    $row['start_date'],
                    $row['end_date'],
                    $row['basic_salary'],
                    $row['status'],
                ]);
            }
            fclose($out);
        }, 'staff-contracts.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
