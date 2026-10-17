<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Models\LibraryTip;
use App\Domains\Library\Models\WriterEarning;
use Illuminate\Support\Facades\DB;

/**
 * BLOG_PLAN BL6a (§7.1): a refunded tip. The tip row is never edited: a new
 * `refunded` row reverses it (a negative amount, `reverses_tip_id`). Its
 * earning is clawed back as a refunded sale's is (L6, §43.7): its status
 * becomes `refunded`, so it never matures or pays out; one already paid out
 * is the office's reconciliation, as for sales. Idempotent.
 */
class ReverseTipAction
{
    public function execute(int $tipId): ?LibraryTip
    {
        return DB::transaction(function () use ($tipId) {
            $tip = LibraryTip::query()->whereKey($tipId)->lockForUpdate()->first();
            if ($tip === null || $tip->status !== 'paid' || $tip->reverses_tip_id !== null) {
                return null;
            }
            if (LibraryTip::query()->where('reverses_tip_id', $tip->id)->exists()) {
                return null;
            }
            $reversal = LibraryTip::query()->create([
                'user_id' => $tip->user_id, 'writer_id' => $tip->writer_id, 'library_item_id' => $tip->library_item_id,
                'amount' => -1 * (float) $tip->amount, 'currency' => $tip->currency, 'method' => $tip->method, 'payment_id' => $tip->payment_id,
                'status' => 'refunded', 'reverses_tip_id' => $tip->id, 'note_status' => 'none', 'paid_at' => now(),
            ]);
            WriterEarning::query()->where('library_tip_id', $tip->id)->update(['status' => 'refunded']);

            return $reversal;
        });
    }
}
