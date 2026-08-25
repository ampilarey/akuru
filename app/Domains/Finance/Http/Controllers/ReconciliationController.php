<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Actions\ListReconciliationAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $report = app(ListReconciliationAction::class)->execute(
            $request->input('from'),
            $request->input('to'),
        );

        return Inertia::render('Finance/Reconciliation/Index', [
            'rows' => $report['rows']->values(),
            'daily' => $report['daily']->values(),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $report = app(ListReconciliationAction::class)->execute(
            $request->input('from'),
            $request->input('to'),
        );

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['receipt', 'payment', 'invoice', 'method', 'amount', 'received_at', 'invoice_balance']);
            foreach ($report['rows'] as $row) {
                fputcsv($out, [
                    $row['receipt_number'],
                    $row['payment_reference'],
                    $row['invoice_number'],
                    $row['method'],
                    $row['amount'],
                    $row['received_at'],
                    $row['invoice_balance'],
                ]);
            }
            fclose($out);
        }, 'reconciliation.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
