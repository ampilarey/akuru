<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Academics\Actions\ListAcademicYearsAction;
use App\Domains\Finance\Actions\CreatePaymentPlanAction;
use App\Domains\Finance\Actions\ListPaymentPlansAction;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Models\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentPlanController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $years = app(ListAcademicYearsAction::class)->execute();
        $yearId = $request->integer('academic_year_id') ?: (int) ($years->firstWhere('is_current', true)['id'] ?? $years->first()['id'] ?? 0);

        $openInvoices = Invoice::query()
            ->where('academic_year_id', $yearId)
            ->whereNull('payment_plan_id')
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value, InvoiceStatus::Draft->value])
            ->whereColumn('paid_amount', '<', 'total_amount')
            ->orderBy('invoice_number')
            ->get(['id', 'invoice_number', 'student_id', 'total_amount', 'paid_amount']);

        return Inertia::render('Finance/PaymentPlans/Index', [
            'years' => $years->values(),
            'yearId' => $yearId,
            'plans' => app(ListPaymentPlansAction::class)->execute($yearId ?: null)->values(),
            'openInvoices' => $openInvoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'balance' => number_format((float) $invoice->total_amount - (float) $invoice->paid_amount, 2, '.', ''),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'academic_year_id' => ['nullable', 'integer'],
            'installments' => ['required', 'array', 'min:1'],
            'installments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'installments.*.due_date' => ['required', 'date'],
        ]);
        $data['created_by'] = $request->user()->id;

        app(CreatePaymentPlanAction::class)->execute($data);

        return redirect()
            ->route('finance.payment-plans.index', array_filter(['academic_year_id' => $data['academic_year_id'] ?? null]))
            ->with('success', 'Payment plan created.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $rows = app(ListPaymentPlansAction::class)->execute($request->integer('academic_year_id') ?: null);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['invoice', 'student', 'total', 'paid', 'status', 'installments']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['invoice_number'],
                    $row['student_name'],
                    $row['total_amount'],
                    $row['paid_amount'],
                    $row['status'],
                    count($row['installments'] ?? []),
                ]);
            }
            fclose($out);
        }, 'payment-plans.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
