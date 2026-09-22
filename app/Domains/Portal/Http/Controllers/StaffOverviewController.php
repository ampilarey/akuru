<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Portal\Actions\ComposeStaffOverviewAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffOverviewController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeOverview($request);

        return Inertia::render(
            'Portal/StaffOverview',
            app(ComposeStaffOverviewAction::class)->execute($this->yearId($request)),
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeOverview($request);
        $composer = app(ComposeStaffOverviewAction::class);
        $rows = $composer->csvRows($composer->execute($this->yearId($request)));

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['section', 'label', 'detail', 'status', 'count', 'rate']);
            foreach ($rows as $row) {
                Csv::put($out, $row);
            }
            fclose($out);
        }, 'staff-overview.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeOverview(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ($user->can('registers.manage') || $user->can('exams.manage')),
            403,
        );
    }

    private function yearId(Request $request): ?int
    {
        $yearId = $request->integer('academic_year_id');

        return $yearId > 0 ? $yearId : null;
    }
}
