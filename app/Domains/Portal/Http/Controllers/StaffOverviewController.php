<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Portal\Actions\ComposeStaffOverviewAction;
use App\Http\Controllers\Controller;
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
        $payload = app(ComposeStaffOverviewAction::class)->execute($this->yearId($request));

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['section', 'label', 'detail', 'status', 'count', 'rate']);

            foreach ($payload['unfilled'] as $row) {
                fputcsv($out, [
                    'unfilled',
                    $row['class_name'] ?? '',
                    trim(($row['subject_name'] ?? '').' '.($row['period_name'] ?? '')),
                    $row['status'] ?? '',
                    $row['date'] ?? '',
                    '',
                ]);
            }
            foreach ($payload['ungraded'] as $row) {
                fputcsv($out, [
                    'ungraded',
                    $row['name'] ?? '',
                    trim(($row['class_name'] ?? '').' '.($row['subject_name'] ?? '')),
                    $row['status'] ?? '',
                    $row['exam_date'] ?? '',
                    '',
                ]);
            }
            foreach ($payload['fillRates'] as $row) {
                fputcsv($out, [
                    'fill_rate',
                    $row['teacher_name'] ?: ('Teacher #'.($row['teacher_id'] ?? '')),
                    '',
                    '',
                    ($row['filled'] ?? '').'/'.($row['total'] ?? ''),
                    $row['rate'] ?? '',
                ]);
            }
            foreach ($payload['planAdherence'] as $row) {
                fputcsv($out, [
                    'plan_adherence',
                    $row['title'] ?? '',
                    '',
                    '',
                    ($row['completed'] ?? '').'/'.($row['total'] ?? ''),
                    $row['rate'] ?? '',
                ]);
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
