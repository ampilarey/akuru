<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\BmlConnectService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile
                            {--older-than=5 : Reconcile payments pending longer than this many minutes}
                            {--not-updated-in=2 : Skip if payment was updated in the last N minutes}';

    protected $description = 'Reconcile pending BML payments by fetching status from the API (fallback when webhook is delayed)';

    public function handle(BmlConnectService $bml): int
    {
        $olderThan = (int) $this->option('older-than');
        $notUpdatedIn = (int) $this->option('not-updated-in');

        $query = Payment::where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes($olderThan))
            ->where('updated_at', '<=', now()->subMinutes($notUpdatedIn));

        $payments = $query->get();
        if ($payments->isEmpty()) {
            $this->info('No pending payments to reconcile.');
            return self::SUCCESS;
        }

        $this->info("Reconciling {$payments->count()} payment(s).");
        $updated = 0;

        foreach ($payments as $payment) {
            // BML Get Transaction (v2) is by their transaction id, not merchant localId
            $reference = $payment->bml_transaction_id ?? $payment->local_id;
            if (! $reference) {
                continue;
            }

            $data = $bml->getTransactionStatus($reference);
            if (! $data) {
                continue;
            }

            // v2 response uses "state" (e.g. CONFIRMED, FAILED)
            $status = $data['state'] ?? $data['status'] ?? $data['transactionStatus'] ?? null;
            if ($status === null) {
                continue;
            }

            $newStatus = $bml->mapWebhookStatusToPaymentStatus((string) $status);
            if ($newStatus === 'pending') {
                continue;
            }

            DB::transaction(function () use ($payment, $newStatus, $data) {
                $payment->lockForUpdate();
                $payment->bml_status_raw = array_merge($payment->bml_status_raw ?? [], $data);
                $payment->status = $newStatus;
                if ($newStatus === 'confirmed') {
                    $payment->paid_at = $payment->paid_at ?? now();
                    $payment->confirmed_at = $payment->confirmed_at ?? $payment->paid_at;
                    foreach ($payment->items as $item) {
                        $enrollment = $item->enrollment;
                        $enrollment->update(['payment_status' => 'confirmed', 'payment_id' => $payment->id]);
                        $course = $enrollment->course;
                        if (! ($course->requires_admin_approval ?? false)) {
                            $enrollment->update([
                                'status' => 'active',
                                'enrolled_at' => $enrollment->enrolled_at ?? now(),
                            ]);
                        }
                    }
                } elseif ($newStatus === 'failed') {
                    $payment->failed_at = now();
                }
                $payment->save();
            });
            $updated++;
        }

        $this->info("Updated {$updated} payment(s).");
        return self::SUCCESS;
    }
}
