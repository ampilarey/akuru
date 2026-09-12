<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Actions\BuildPaymentReceiptAction;
use App\Domains\Finance\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * SPEC §45 lists "Manage payments" among the areas the backend must enforce,
 * and this screen is where a family reads back what they paid.
 *
 * It never worked. The gate read:
 *
 *     if (! in_array($payment->status, ['paid', 'completed'])) { abort(404); }
 *
 * and **neither value exists**. The `payments.status` column is an enum of
 * `initiated, pending, confirmed, failed, cancelled, expired, refunded`, and
 * the only status the code ever writes for money received is `confirmed`
 * (`PaymentService` on webhook confirmation, `RecordManualPaymentAction` for
 * money taken by hand). So every receipt for every payment ever made returned
 * 404 — to the payer, to an admin, to everyone — and the ownership rule above
 * it never got to run at all.
 *
 * Nothing linked to the route from any screen, which is why no walk caught it
 * and why it stayed broken: the page could only be reached by typing the URL.
 */
class PaymentReceiptController extends Controller
{
    public function show(Request $request, Payment $payment): View|Response
    {
        $user = $request->user();

        // §45 asks for *permissions*, not roles. `finance.manage` is what
        // governs the rest of Finance, so it governs here too — which
        // deliberately narrows staff access: `supervisor` used to be listed by
        // role and does not hold `finance.manage`. An academic supervisor has
        // no reason to read a family's payment receipts.
        $isStaff = (bool) $user?->can('finance.manage');

        if (! $isStaff && (int) $payment->user_id !== (int) $user?->id) {
            abort(403);
        }

        if (! app(BuildPaymentReceiptAction::class)->isReceiptable($payment)) {
            abort(404, 'Receipt not available for this payment.');
        }

        return view('payments.receipt', [
            'payment' => $payment,
            'receipt' => app(BuildPaymentReceiptAction::class)->execute($payment),
        ]);
    }
}
