<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\HR\Actions\ListCpdRecordsAction;
use App\Domains\HR\Actions\SaveCpdRecordAction;
use App\Domains\People\Actions\ListStaffProfilesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CpdRecordController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        return Inertia::render('HR/Performance/Cpd', [
            'staff' => app(ListStaffProfilesAction::class)->execute(['status' => 'active'])->values(),
            'rows' => app(ListCpdRecordsAction::class)->execute($request->integer('staff_profile_id') ?: null)->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        app(SaveCpdRecordAction::class)->execute($request->validate([
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'title' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'hours' => ['required', 'numeric', 'min:0'],
            'date' => ['nullable', 'date'],
        ]));

        return redirect()->route('hr.cpd.index')->with('success', 'CPD record saved.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $rows = app(ListCpdRecordsAction::class)->execute();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['staff_name', 'title', 'provider', 'hours', 'date']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['staff_name'], $row['title'], $row['provider'], $row['hours'], $row['date']]);
            }
            fclose($out);
        }, 'cpd-records.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
