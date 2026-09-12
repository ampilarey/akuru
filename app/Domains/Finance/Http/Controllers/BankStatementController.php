<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Actions\ConfirmBankStatementMatchAction;
use App\Domains\Finance\Actions\IgnoreBankStatementLineAction;
use App\Domains\Finance\Actions\ImportBankStatementAction;
use App\Domains\Finance\Actions\ListBankStatementLinesAction;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Models\BankStatementLine;
use App\Domains\Finance\Models\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Two permissions on purpose, because two different things happen here.
 *
 * Reading and uploading a statement is `finance.manage` — it is bookkeeping.
 * **Confirming** a match writes a receipt, so it needs
 * `finance.record-manual-payment`, the same permission the cashier screen
 * demands. Somebody who may look at the bank's file is not thereby somebody who
 * may decide the school has been paid.
 */
class BankStatementController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $payload = app(ListBankStatementLinesAction::class)->execute(
            $request->filled('import') ? (int) $request->input('import') : null,
            $request->filled('status') ? (string) $request->input('status') : null,
        );

        return Inertia::render('Finance/BankStatements/Index', [
            ...$payload,
            'open_invoices' => Invoice::query()
                ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value, InvoiceStatus::Draft->value])
                ->whereColumn('paid_amount', '<', 'total_amount')
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'invoice_number', 'total_amount', 'paid_amount'])
                ->map(fn (Invoice $invoice): array => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'balance' => number_format((float) $invoice->total_amount - (float) $invoice->paid_amount, 2, '.', ''),
                ])
                ->values(),
            'can_confirm' => (bool) $request->user()?->can('finance.record-manual-payment'),
            // Surfaced so the screen can say which columns it expects rather
            // than making somebody read the config to find out why their file
            // was rejected.
            'expected_columns' => array_values(array_filter((array) config('finance.bank_statement.columns'))),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'account_label' => ['nullable', 'string', 'max:120'],
        ]);

        $result = app(ImportBankStatementAction::class)->execute(
            (string) file_get_contents($data['file']->getRealPath()),
            (string) $data['file']->getClientOriginalName(),
            (int) $request->user()->id,
            $data['account_label'] ?? null,
        );

        $message = $result['duplicate_file']
            ? 'That statement was already imported — showing the existing one.'
            : sprintf(
                '%d line(s) imported, %d suggested match(es), %d left ambiguous.',
                $result['created'],
                $result['suggested'],
                $result['ambiguous'],
            );

        return redirect()
            ->route('finance.bank-statements.index', ['import' => $result['import']->id])
            ->with('success', $message);
    }

    public function confirm(Request $request, BankStatementLine $line): RedirectResponse
    {
        abort_unless($request->user()?->can('finance.record-manual-payment'), 403);

        $data = $request->validate([
            'invoice_id' => ['nullable', 'integer'],
        ]);

        app(ConfirmBankStatementMatchAction::class)->execute(
            $line,
            (int) $request->user()->id,
            isset($data['invoice_id']) ? (int) $data['invoice_id'] : null,
        );

        return back()->with('success', 'Receipt recorded against the invoice.');
    }

    public function ignore(Request $request, BankStatementLine $line): RedirectResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        app(IgnoreBankStatementLineAction::class)->execute(
            $line,
            (int) $request->user()->id,
            $data['reason'] ?? null,
        );

        return back()->with('success', 'Line marked as not a school payment.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $payload = app(ListBankStatementLinesAction::class)->execute(
            $request->filled('import') ? (int) $request->input('import') : null,
            $request->filled('status') ? (string) $request->input('status') : null,
        );

        return response()->streamDownload(function () use ($payload): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['posted_on', 'description', 'reference', 'amount', 'match_status', 'invoice_number', 'note']);
            foreach ($payload['lines'] as $row) {
                fputcsv($handle, [
                    $row['posted_on'],
                    $row['description'],
                    $row['reference'],
                    $row['amount'],
                    $row['match_status'],
                    $row['invoice_number'],
                    $row['match_note'],
                ]);
            }
            fclose($handle);
        }, 'bank-statement-lines.csv', ['Content-Type' => 'text/csv']);
    }
}
