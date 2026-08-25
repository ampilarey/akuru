<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\HR\Actions\ExpiringDocumentsReportAction;
use App\Domains\HR\Actions\NotifyExpiringDocumentsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplianceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $within = $request->integer('within') ?: 90;

        return Inertia::render('HR/Compliance/Index', [
            'within' => $within,
            'rows' => app(ExpiringDocumentsReportAction::class)->execute($within)->values(),
        ]);
    }

    public function notify(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $sent = app(NotifyExpiringDocumentsAction::class)->execute();

        return redirect()->route('hr.compliance.index')->with('success', $sent.' expiry notices sent.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $rows = app(ExpiringDocumentsReportAction::class)->execute($request->integer('within') ?: 90);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['staff_name', 'title', 'document_type', 'expires_at', 'days_until']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['staff_name'],
                    $row['title'],
                    $row['document_type'],
                    $row['expires_at'],
                    $row['days_until'],
                ]);
            }
            fclose($out);
        }, 'expiring-documents.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
