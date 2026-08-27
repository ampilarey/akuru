<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Actions\RefundPaymentAction;
use App\Domains\Finance\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminPaymentRefundController extends Controller
{
    /** P4.3: record a refund against a confirmed payment. */
    public function store(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'destination' => ['required', 'in:wallet,manual'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        app(RefundPaymentAction::class)->execute(
            $payment->id,
            (float) $data['amount'],
            $data['destination'],
            $request->user()->id,
            $data['reason'] ?? null,
        );

        return back()->with('success', 'Refund recorded.');
    }
}
