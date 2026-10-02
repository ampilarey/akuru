<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\ListLibraryInsightsAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * B14 (LIBRARY_PLAN §29): the Library over a period, for the office. Gated
 * with the rest of the Library office (`role:super_admin` + `library.manage`).
 */
class AdminLibraryInsightsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Library/Insights', [
            'insights' => app(ListLibraryInsightsAction::class)->execute((string) $request->query('period', 'month')),
            'periods' => array_keys(ListLibraryInsightsAction::PERIODS),
            't' => Phrases::once('admin'),
        ]);
    }

    /** Every listing gets a CSV: the most-read table for the period. */
    public function export(Request $request): StreamedResponse
    {
        $insights = app(ListLibraryInsightsAction::class)->execute((string) $request->query('period', 'month'));

        return response()->streamDownload(function () use ($insights): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['period', 'title', 'category', 'writer', 'pages_opened', 'readers', 'completions', 'purchases']);
            foreach ($insights['most_read'] as $row) {
                Csv::put($out, [$insights['period'], $row['title'], $row['category'], $row['writer'], $row['pages'], $row['readers'], $row['completions'], $row['purchases']]);
            }
            fclose($out);
        }, 'library-insights-'.$insights['period'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
