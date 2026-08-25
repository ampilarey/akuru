<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Academics\Actions\ListAcademicYearsAction;
use App\Domains\Finance\Actions\ListArrearsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArrearsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $years = app(ListAcademicYearsAction::class)->execute();
        $yearId = $request->integer('academic_year_id') ?: (int) ($years->firstWhere('is_current', true)['id'] ?? $years->first()['id'] ?? 0);

        return Inertia::render('Finance/Arrears/Index', [
            'years' => $years->values(),
            'yearId' => $yearId,
            'rows' => app(ListArrearsAction::class)->execute($yearId ?: null)->values(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $rows = app(ListArrearsAction::class)->execute($request->integer('academic_year_id') ?: null);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['number', 'student', 'class_id', 'guardian', 'due_date', 'balance', 'days', 'bucket']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['invoice_number'],
                    $row['student_name'],
                    $row['class_id'],
                    $row['guardian_name'],
                    $row['due_date'],
                    $row['balance'],
                    $row['days_overdue'],
                    $row['aging_bucket'],
                ]);
            }
            fclose($out);
        }, 'arrears.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
