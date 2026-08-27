<?php

namespace App\Console\Commands;

use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentService;
use Illuminate\Console\Command;

class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile
                            {--older-than=5 : Reconcile payments pending longer than this many minutes}
                            {--not-updated-in=2 : Skip if payment was updated in the last N minutes}';

    protected $description = 'Reconcile pending BML payments by fetching status from the API (fallback when webhook is delayed)';

    public function handle(PaymentService $paymentService): int
    {
        $olderThan = (int) $this->option('older-than');
        $notUpdatedIn = (int) $this->option('not-updated-in');

        $pending = Payment::where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes($olderThan))
            ->where('updated_at', '<=', now()->subMinutes($notUpdatedIn))
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending payments to reconcile.');

            return self::SUCCESS;
        }

        $this->info("Reconciling {$pending->count()} payment(s).");
        $updated = 0;

        foreach ($pending as $payment) {
            // P4.2: one confirmation path. finalizeByReference locks the row,
            // queries the provider (by bml_transaction_id when known), and on
            // success runs the SAME pipeline as the webhook — PaymentConfirmed
            // event, notifications, funnel — instead of a private copy that
            // silently activated enrollments and granted nothing else.
            $result = $paymentService->finalizeByReference($payment->merchant_reference);

            if ($result && $result->status !== 'pending') {
                $updated++;
            }
        }

        $this->info("Updated {$updated} payment(s).");

        return self::SUCCESS;
    }
}
