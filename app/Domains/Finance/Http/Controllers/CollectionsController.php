<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Academics\Actions\ListAcademicYearsAction;
use App\Domains\Finance\Actions\ListCollectionsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollectionsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $years = app(ListAcademicYearsAction::class)->execute();
        $yearId = $request->integer('academic_year_id') ?: (int) ($years->firstWhere('is_current', true)['id'] ?? $years->first()['id'] ?? 0);

        return Inertia::render('Finance/Collections/Index', [
            'years' => $years->values(),
            'yearId' => $yearId,
            'rows' => app(ListCollectionsAction::class)->execute($yearId ?: null)->values(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $rows = app(ListCollectionsAction::class)->execute($request->integer('academic_year_id') ?: null);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['class_id', 'month', 'billed', 'collected', 'invoices']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['class_id'], $row['month'], $row['billed'], $row['collected'], $row['invoice_count']]);
            }
            fclose($out);
        }, 'collections.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
