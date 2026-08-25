<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Models\Receipt;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Http\Controllers\Controller;
use App\Support\Contracts\DocumentRendererInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReceiptDocumentController extends Controller
{
    public function show(Request $request, Receipt $receipt): Response
    {
        $receipt->load('invoice');
        $user = $request->user();
        abort_unless($user !== null, 403);

        if (! $user->can('finance.manage') && ! $user->can('finance.record-manual-payment')) {
            $childIds = app(ListGuardianChildrenAction::class)
                ->executeForGuardianUserId((int) $user->id)
                ->pluck('id')
                ->all();
            abort_unless(in_array($receipt->invoice?->student_id, $childIds, true), 403);
        }

        $html = app(DocumentRendererInterface::class)->render('finance.receipt', [
            'title' => 'Receipt '.$receipt->receipt_number,
            'invoice' => $receipt->invoice?->invoice_number,
            'amount' => $receipt->amount,
            'method' => $receipt->method?->value,
            'received_at' => $receipt->received_at?->timezone('Indian/Maldives')->toDateTimeString(),
        ]);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
