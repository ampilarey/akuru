<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Actions\RecordInvoiceReceiptAction;
use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Enums\ReceiptMethod;
use App\Domains\Finance\Models\Invoice;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
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
            ->get(['id', 'invoice_number', 'student_id', 'total_amount', 'paid_amount', 'notes']);
        // An open invoice says whose it is: the office chose by number alone.
        $names = app(ListStudentsByIdsAction::class)->execute($open->pluck('student_id')->filter()->all())->keyBy('id');

        return Inertia::render('Finance/Receipts/Manual', [
            'invoices' => $open->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'student_name' => $names[$invoice->student_id]['name'] ?? $invoice->notes,
                'balance' => number_format((float) $invoice->total_amount - (float) $invoice->paid_amount, 2, '.', ''),
            ])->values(),
            'methods' => [ReceiptMethod::Cash->value, ReceiptMethod::Transfer->value],
            't' => Phrases::once('finance'),
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

        return redirect()->route('finance.receipts.manual')->with('success', __('finance.flash_receipt_recorded'));
    }
}
