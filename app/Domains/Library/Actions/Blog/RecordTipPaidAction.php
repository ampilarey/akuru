<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Actions\ResolveLibrarySettingAction;
use App\Domains\Library\Models\LibraryTip;
use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterProfile;
use App\Support\Said;
use Illuminate\Support\Facades\DB;

/**
 * BLOG_PLAN BL6a (§7.1): a tip is paid — from BML's **webhook** for a card
 * tip, or at once for a wallet one — and earns once. The earning is the tip
 * less Akuru's share (the `tip_share_percent` setting), maturing after the
 * refund window like a sale's. Idempotent: a webhook retry writes nothing
 * new.
 */
class RecordTipPaidAction
{
    public function execute(int $tipId): ?WriterEarning
    {
        $earning = DB::transaction(function () use ($tipId) {
            $tip = LibraryTip::query()->whereKey($tipId)->lockForUpdate()->first();
            if ($tip === null || $tip->reverses_tip_id !== null || ! in_array($tip->status, ['pending', 'paid'], true)) {
                return null;
            }
            if ($tip->status === 'pending') {
                $tip->fill(['status' => 'paid', 'paid_at' => now()])->save();
            }
            if (WriterEarning::query()->where('library_tip_id', $tip->id)->exists()) {
                return null;
            }
            $amount = round((float) $tip->amount, 2);
            $share = max(0, min(100, (int) app(ResolveLibrarySettingAction::class)->execute('tip_share_percent')));
            $writerAmount = round($amount * (100 - $share) / 100, 2);

            return WriterEarning::query()->create([
                'writer_id' => $tip->writer_id,
                'kind' => 'tip',
                'library_item_id' => $tip->library_item_id,
                'library_tip_id' => $tip->id,
                'gross_amount' => $amount,
                'discount_amount' => 0,
                'wallet_amount' => $tip->method === 'wallet' ? $amount : 0,
                'bml_amount' => $tip->method === 'card' ? $amount : 0,
                'platform_commission' => round($amount - $writerAmount, 2),
                'writer_amount' => $writerAmount,
                'status' => 'pending',
                'available_at' => ($tip->paid_at ?? now())->copy()->addDays((int) app(ResolveLibrarySettingAction::class)->execute('refund_window_days')),
            ]);
        });

        if ($earning !== null) {
            $writer = WriterProfile::query()->find($earning->writer_id);
            if ($writer?->user_id) {
                app(NotifyLibraryUserAction::class)->execute(
                    (int) $writer->user_id,
                    Said::of('library_notices.tip_title'), Said::of('library_notices.tip_body', ['gross' => number_format((float) $earning->gross_amount, 2), 'share' => number_format((float) $earning->writer_amount, 2)]),
                    '/my-blog',
                    'new_sale',
                );
            }
        }

        return $earning;
    }
}
