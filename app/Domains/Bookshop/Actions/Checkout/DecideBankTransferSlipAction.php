<?php

namespace App\Domains\Bookshop\Actions\Checkout;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Enums\SlipStatus;
use App\Domains\Bookshop\Models\BankTransferSlip;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The office reads the slip against the bank account and confirms it —
 * the checkout is paid — or rejects it with a reason, and the customer
 * may upload another while the checkout still waits. B3 lets the vendor
 * of a single-vendor checkout confirm as well.
 */
class DecideBankTransferSlipAction
{
    public function execute(int $slipId, bool $confirm, int $byUserId, ?string $note = null): BankTransferSlip
    {
        $slip = DB::transaction(function () use ($slipId, $confirm, $byUserId, $note) {
            $slip = BankTransferSlip::query()->whereKey($slipId)->lockForUpdate()->firstOrFail();
            if ($slip->status !== SlipStatus::Waiting) {
                throw ValidationException::withMessages(['slip' => __('shop.error_slip_decided')]);
            }
            $slip->update([
                'status' => ($confirm ? SlipStatus::Confirmed : SlipStatus::Rejected)->value,
                'decided_by' => $byUserId,
                'decided_at' => now(),
                'decision_note' => trim((string) $note) ?: null,
            ]);

            return $slip;
        });

        $checkout = $slip->checkout;
        if ($confirm) {
            app(MarkCheckoutPaidAction::class)->execute((int) $checkout->id, 'bank_transfer', $byUserId);
        } else {
            app(NotifyBookshopUserAction::class)->execute(
                (int) $checkout->user_id,
                __('shop.notice_slip_rejected_title'),
                __('shop.notice_slip_rejected_body', ['number' => $checkout->number, 'note' => trim((string) $note)]),
                '/shop/checkout/'.$checkout->number,
            );
        }

        return $slip->refresh();
    }
}
