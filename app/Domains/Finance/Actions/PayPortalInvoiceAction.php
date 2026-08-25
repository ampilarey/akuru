<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Services\Payment\PaymentService;
use Illuminate\Validation\ValidationException;

class PayPortalInvoiceAction
{
    /**
     * @param  list<int>  $allowedStudentIds
     * @return array{redirect_url: ?string, error: ?string}
     */
    public function execute(int $invoiceId, int $userId, array $allowedStudentIds, string $mode = 'full'): array
    {
        $invoice = Invoice::query()->find($invoiceId);
        if ($invoice === null || ! in_array($invoice->student_id, $allowedStudentIds, true)) {
            throw ValidationException::withMessages(['invoice_id' => 'Invoice is not available.']);
        }

        $initiated = app(InitiateInvoicePaymentAction::class)->execute($invoice, $userId, $mode);
        $result = app(PaymentService::class)->initiatePayment($initiated['payment'], [
            'return_url' => route('payments.bml.return').'?ref='.$initiated['payment']->merchant_reference,
        ]);

        return [
            'redirect_url' => $result->success ? $result->redirectUrl : null,
            'error' => $result->success ? null : ($result->error ?? 'Payment initiation failed.'),
        ];
    }
}
