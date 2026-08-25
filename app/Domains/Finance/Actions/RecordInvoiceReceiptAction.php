<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\ReceiptMethod;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Media\Actions\StoreRenderedDocumentAction;
use App\Support\Contracts\DocumentRendererInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordInvoiceReceiptAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Receipt
    {
        $invoice = Invoice::query()->find((int) ($data['invoice_id'] ?? 0));
        if ($invoice === null) {
            throw ValidationException::withMessages(['invoice_id' => 'Invoice not found.']);
        }

        $paymentId = isset($data['payment_id']) ? (int) $data['payment_id'] : null;
        if ($paymentId && Receipt::query()->where('payment_id', $paymentId)->exists()) {
            return Receipt::query()->where('payment_id', $paymentId)->firstOrFail();
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        $method = ReceiptMethod::from((string) ($data['method'] ?? ReceiptMethod::Cash->value));

        return DB::transaction(function () use ($invoice, $paymentId, $amount, $method, $data) {
            app(AllocatePaymentAction::class)->execute($invoice->fresh(), $amount);

            $receipt = Receipt::query()->create([
                'invoice_id' => $invoice->id,
                'payment_id' => $paymentId ?: null,
                'receipt_number' => app(NextReceiptNumberAction::class)->execute(),
                'amount' => $amount,
                'method' => $method,
                'received_by' => isset($data['received_by']) ? (int) $data['received_by'] : null,
                'received_at' => $data['received_at'] ?? now('Indian/Maldives'),
            ]);

            $html = app(DocumentRendererInterface::class)->render('finance.receipt', [
                'title' => 'Receipt '.$receipt->receipt_number,
                'invoice' => $invoice->invoice_number,
                'amount' => number_format($amount, 2, '.', ''),
                'method' => $method->value,
                'received_at' => $receipt->received_at?->timezone('Indian/Maldives')->toDateTimeString(),
            ]);

            $stored = app(StoreRenderedDocumentAction::class)->execute(
                'receipt',
                $receipt->id,
                'Receipt '.$receipt->receipt_number,
                $html,
                $receipt->received_by,
                'receipt',
            );
            $receipt->document_id = $stored['id'];
            $receipt->save();

            return $receipt->fresh();
        });
    }

    public function fromConfirmedPayment(Payment $payment): ?Receipt
    {
        if (! $payment->isConfirmed()) {
            return null;
        }

        $invoiceId = (int) $payment->payable_id;
        $isInvoice = in_array($payment->payable_type, ['invoice', Invoice::class], true);
        if (! $isInvoice || $invoiceId < 1) {
            return null;
        }

        return $this->execute([
            'invoice_id' => $invoiceId,
            'payment_id' => $payment->id,
            'amount' => (float) $payment->amount,
            'method' => ReceiptMethod::Bml->value,
            'received_by' => $payment->user_id,
            'received_at' => $payment->paid_at ?? now('Indian/Maldives'),
        ]);
    }
}
