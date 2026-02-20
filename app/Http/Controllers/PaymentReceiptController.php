<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PaymentReceiptController extends Controller
{
    public function show(Request $request, Payment $payment): View|Response
    {
        // Only the payer or an admin can view a receipt
        $user = $request->user();
        $isAdmin = $user?->hasAnyRole(['super_admin', 'admin', 'headmaster', 'supervisor']);

        if (! $isAdmin && $payment->user_id !== $user?->id) {
            abort(403);
        }

        // Only show receipt for paid payments
        if (! in_array($payment->status, ['paid', 'completed'])) {
            abort(404, 'Receipt not available for this payment.');
        }

        $payment->loadMissing(['user', 'student', 'items.course', 'items.enrollment']);

        return view('payments.receipt', compact('payment'));
    }
}
