<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Enums\EarningStatus;
use App\Domains\Bookshop\Enums\PayoutStatus;
use App\Domains\Bookshop\Models\VendorEarning;
use App\Domains\Bookshop\Models\VendorPayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The office pays a requested payout by bank transfer and records the
 * transfer's reference (BOOKSHOP_PLAN §7 "Payouts: requests, decide"), or
 * declines it with a note. Paid: every earning tied to it is settled to
 * its current net (a clawback settles to the lower figure). Declined: the
 * earnings are free for the next request. Requested payouts only.
 */
class DecideVendorPayoutAction
{
    public function execute(int $payoutId, int $officeUserId, bool $paid, ?string $reference = null, ?string $note = null): VendorPayout
    {
        $reference = trim((string) $reference) ?: null;
        $note = trim((string) $note) ?: null;
        if ($paid && $reference === null) {
            throw ValidationException::withMessages(['reference' => __('shop.error_payout_reference')]);
        }
        if (! $paid && $note === null) {
            throw ValidationException::withMessages(['note' => __('shop.error_payout_note')]);
        }

        $payout = DB::transaction(function () use ($payoutId, $officeUserId, $paid, $reference, $note) {
            $payout = VendorPayout::query()->whereKey($payoutId)->lockForUpdate()->firstOrFail();
            if ($payout->status !== PayoutStatus::Requested) {
                throw ValidationException::withMessages(['payout' => __('shop.error_payout_decided')]);
            }
            $payout->update([
                'status' => ($paid ? PayoutStatus::Paid : PayoutStatus::Rejected)->value,
                'decided_by' => $officeUserId,
                'decided_at' => now(),
                'reference' => $reference,
                'note' => $note,
            ]);
            foreach (VendorEarning::query()->where('open_payout_id', $payout->id)->lockForUpdate()->get() as $earning) {
                if ($paid) {
                    $earning->update([
                        'paid_amount' => $earning->net,
                        'paid_at' => now(),
                        'status' => $earning->status === EarningStatus::Reversed ? EarningStatus::Reversed->value : EarningStatus::Paid->value,
                        'open_payout_id' => null,
                        'last_payout_id' => $payout->id,
                    ]);
                } else {
                    $earning->update(['open_payout_id' => null]);
                }
            }

            return $payout->refresh();
        });

        app(NotifyBookshopUserAction::class)->vendor(
            (int) $payout->vendor_id,
            __($paid ? 'shop.notice_payout_paid_title' : 'shop.notice_payout_rejected_title'),
            __($paid ? 'shop.notice_payout_paid_body' : 'shop.notice_payout_rejected_body', ['amount' => $payout->currency.' '.number_format((float) $payout->amount, 2), 'reference' => (string) $reference, 'note' => (string) $note]),
            '/vendor/money',
        );

        return $payout;
    }
}
