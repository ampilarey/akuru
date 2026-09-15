<?php

namespace App\Domains\Finance\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Phase 4 cleanup gate (rule 9, deploy 3): may the legacy
 * `enrollment_pending_payload` safety net be deleted yet?
 *
 * P4.2 stopped *writing* the column — `createPaymentForPendingEnrollment` is
 * gone — but the webhook still **reads** it, so a payment started before that
 * deploy can still be finalized. Rule 9's third deploy deletes that read branch
 * and drops the column, and it may only run once nothing depends on it.
 *
 * STATUS §5h recorded the gate as a line of SQL to copy out and run:
 *
 *     SELECT COUNT(*) FROM payments
 *      WHERE enrollment_pending_payload IS NOT NULL
 *        AND status NOT IN ('confirmed','paid','failed','cancelled','expired')
 *
 * ## Why that number alone is not an answer
 *
 * It returns `0` on a database that has drained, and `0` on a database that
 * never had a single pre-P4.2 payment — a fresh install, a demo host, a local
 * dev box. Those are opposite situations and the query cannot tell them apart,
 * so "0, safe to clean up" is only evidence when you also know the column was
 * ever in use here.
 *
 * That is the same trap `halaqa:verify-structure` sets when it is run without
 * the seeded dataset and reports `programs=0 … OK` (STATUS §5en). So this
 * reports the denominators too, and says which of the two zeros it found.
 */
class VerifyPendingPayloadDrainAction
{
    /**
     * Statuses that mean the payment can no longer act on its payload: it
     * either completed or it is over. Anything else — `initiated`, `pending` —
     * could still arrive at the webhook and need the read branch.
     *
     * **Two corrections to the list STATUS §5h recorded**
     * (`'confirmed','paid','failed','cancelled','expired'`), found by writing
     * a test that inserts one payment per status:
     *
     * - **`paid` does not exist.** The column is
     *   `enum('initiated','pending','confirmed','failed','cancelled','expired','refunded')`,
     *   so `status IN (… 'paid' …)` was matching nothing. Harmless, but it is
     *   noise in a gate somebody is meant to trust.
     * - **`refunded` was missing**, and that one bites: a refunded payment has
     *   already been through confirmation and can never be confirmed again, so
     *   it is finished with its payload — but the recorded query counts it as
     *   blocking and would hold the cleanup deploy open **forever** on a
     *   payment that is unambiguously done.
     *
     * @var list<string>
     */
    public const SETTLED = ['confirmed', 'failed', 'cancelled', 'expired', 'refunded'];

    /**
     * @return array{
     *     ok: bool,
     *     vacuous: bool,
     *     payments: int,
     *     with_payload: int,
     *     blocking: int,
     *     blocking_rows: list<array<string, mixed>>
     * }
     */
    public function execute(): array
    {
        $payments = (int) DB::table('payments')->count();
        $withPayload = (int) DB::table('payments')->whereNotNull('enrollment_pending_payload')->count();

        $blockingQuery = DB::table('payments')
            ->whereNotNull('enrollment_pending_payload')
            ->whereNotIn('status', self::SETTLED);

        $blocking = (int) $blockingQuery->count();

        return [
            'ok' => $blocking === 0,
            // Nothing here ever used the safety net, so a green result says
            // nothing about whether it is safe to delete *elsewhere*.
            'vacuous' => $withPayload === 0,
            'payments' => $payments,
            'with_payload' => $withPayload,
            'blocking' => $blocking,
            // Listed, never guessed — the same rule the halaqa gates follow.
            'blocking_rows' => $blockingQuery
                ->orderBy('id')
                ->limit(50)
                ->get(['id', 'merchant_reference', 'status', 'created_at'])
                ->map(fn ($row): array => [
                    'id' => (int) $row->id,
                    // `merchant_reference` is what the payments screen and the
                    // CSV call the reference; there is no bare `reference`
                    // column, which the first version of this assumed.
                    'reference' => (string) ($row->merchant_reference ?? ''),
                    'status' => (string) $row->status,
                    'created_at' => (string) $row->created_at,
                ])
                ->all(),
        ];
    }
}
