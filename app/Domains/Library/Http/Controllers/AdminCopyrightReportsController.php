<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\DecideCopyrightReportAction;
use App\Domains\Library\Actions\ListCopyrightReportsAction;
use App\Domains\Library\Models\LibraryCopyrightReport;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** BP3 (BACKLOG B11): the office's copyright reports — the list, a decision on each, the CSV. */
class AdminCopyrightReportsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('library.manage'), 403);

        return Inertia::render('Library/CopyrightReports', app(ListCopyrightReportsAction::class)->execute(! $request->boolean('all')) + ['t' => Phrases::once('library_copyright')]);
    }

    public function decide(Request $request, int $report): RedirectResponse
    {
        abort_unless($request->user()?->can('library.manage'), 403);
        $data = $request->validate(['outcome' => 'required|string|max:20', 'note' => 'nullable|string|max:1000']);
        app(DecideCopyrightReportAction::class)->execute(LibraryCopyrightReport::query()->findOrFail($report), $data['outcome'], (string) ($data['note'] ?? ''), (int) $request->user()->id);

        return back()->with('success', __('library_copyright.flash_decided'));
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('library.manage'), 403);
        $payload = app(ListCopyrightReportsAction::class)->execute(! $request->boolean('all'));

        return response()->streamDownload(function () use ($payload): void {
            $handle = fopen('php://output', 'w');
            Csv::put($handle, ['work', 'name', 'email', 'their_work', 'statement', 'status', 'note', 'reported_at', 'decided_at']);
            foreach ($payload['reports'] as $r) {
                Csv::put($handle, [$r['item_title'], $r['name'], $r['email'], $r['work_claimed'], $r['statement'], $r['status'], $r['decision_note'], $r['reported_at'], $r['decided_at']]);
            }
            fclose($handle);
        }, 'library-copyright-reports.csv', ['Content-Type' => 'text/csv']);
    }
}
