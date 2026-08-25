<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\HR\Actions\ListLeaveTypesAction;
use App\Domains\HR\Actions\SaveLeaveTypeAction;
use App\Domains\HR\Enums\LeaveTypeCode;
use App\Domains\HR\Models\LeaveType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveTypeController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        return Inertia::render('HR/Leave/Types', [
            'types' => app(ListLeaveTypesAction::class)->execute()->values(),
            'codes' => array_map(fn (LeaveTypeCode $code) => $code->value, LeaveTypeCode::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        app(SaveLeaveTypeAction::class)->execute($this->validated($request));

        return redirect()->route('hr.leave-types.index')->with('success', 'Leave type saved.');
    }

    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        app(SaveLeaveTypeAction::class)->execute($this->validated($request), $leaveType);

        return redirect()->route('hr.leave-types.index')->with('success', 'Leave type updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $rows = app(ListLeaveTypesAction::class)->execute();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['code', 'name', 'days_per_year', 'carry_over_max', 'paid', 'active']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['code'],
                    $row['name'],
                    $row['days_per_year'],
                    $row['carry_over_max'],
                    $row['paid'] ? '1' : '0',
                    $row['active'] ? '1' : '0',
                ]);
            }
            fclose($out);
        }, 'leave-types.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'code' => ['required', Rule::enum(LeaveTypeCode::class)],
            'name' => ['required', 'string', 'max:255'],
            'name_arabic' => ['nullable', 'string', 'max:255'],
            'name_dhivehi' => ['nullable', 'string', 'max:255'],
            'days_per_year' => ['required', 'numeric', 'min:0'],
            'carry_over_max' => ['nullable', 'numeric', 'min:0'],
            'requires_document' => ['sometimes', 'boolean'],
            'paid' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
