<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterPayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * L6 (§7.7 — admin marks payouts paid): paid → linked earnings flip to
 * paid with a timestamp; rejected → earnings unlink and stay available.
 * Requested payouts only.
 */
class DecideWriterPayoutAction
{
    public function execute(int $payoutId, int $decidedBy, bool $paid, ?string $note = null): WriterPayout
    {
        return DB::transaction(function () use ($payoutId, $decidedBy, $paid, $note) {
            $payout = WriterPayout::query()->whereKey($payoutId)->lockForUpdate()->firstOrFail();
            if ($payout->status !== 'requested') {
                throw ValidationException::withMessages(['payout' => 'This payout has already been decided.']);
            }

            $payout->fill([
                'status' => $paid ? 'paid' : 'rejected',
                'decided_by' => $decidedBy,
                'decided_at' => now(),
                'note' => $note,
            ])->save();

            if ($paid) {
                WriterEarning::query()
                    ->where('writer_payout_id', $payout->id)
                    ->update(['status' => 'paid', 'paid_at' => now()]);
            } else {
                WriterEarning::query()
                    ->where('writer_payout_id', $payout->id)
                    ->update(['writer_payout_id' => null]);
            }

            return $payout->refresh();
        });
    }
}
