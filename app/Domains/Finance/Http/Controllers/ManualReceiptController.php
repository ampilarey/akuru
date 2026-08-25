<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Actions\RecordInvoiceReceiptAction;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Enums\ReceiptMethod;
use App\Domains\Finance\Models\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManualReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('finance.record-manual-payment'), 403);

        $open = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value, InvoiceStatus::Draft->value])
            ->whereColumn('paid_amount', '<', 'total_amount')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'invoice_number', 'total_amount', 'paid_amount']);

        return Inertia::render('Finance/Receipts/Manual', [
            'invoices' => $open->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'balance' => number_format((float) $invoice->total_amount - (float) $invoice->paid_amount, 2, '.', ''),
            ])->values(),
            'methods' => [ReceiptMethod::Cash->value, ReceiptMethod::Transfer->value],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('finance.record-manual-payment'), 403);

        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'in:cash,transfer'],
        ]);
        $data['received_by'] = $request->user()->id;

        app(RecordInvoiceReceiptAction::class)->execute($data);

        return redirect()->route('finance.receipts.manual')->with('success', 'Receipt recorded.');
    }
}
