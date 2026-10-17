<?php

namespace App\Domains\Library\Actions;

use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterPayout;
use App\Domains\Library\Models\WriterProfile;
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
                throw ValidationException::withMessages(['payout' => __('admin.library_office_error_payout_decided')]);
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
                // BLOG_PLAN BL6b: a wallet payout is paid here, into the named
                // wallet (a student's parent's while under 18), naming both.
                if ($payout->method === 'wallet' && $payout->wallet_user_id !== null) {
                    $writerName = (string) WriterProfile::query()->whereKey($payout->writer_id)->value('display_name');
                    app(CreditWalletAction::class)->execute(
                        (int) $payout->wallet_user_id,
                        (float) $payout->amount,
                        'library_payout',
                        $payout->id,
                        'Blog earnings of '.$writerName.' (payout '.$payout->id.')',
                    );
                    if ((int) $payout->wallet_user_id !== (int) WriterProfile::query()->whereKey($payout->writer_id)->value('user_id')) {
                        app(NotifyLibraryUserAction::class)->execute(
                            (int) $payout->wallet_user_id,
                            'Blog earnings in your wallet',
                            'MVR '.number_format((float) $payout->amount, 2).' earned by '.$writerName.'\'s blog is now in your Akuru wallet.',
                            '/my-wallet',
                            'payout_decided',
                        );
                    }
                }
            } else {
                WriterEarning::query()
                    ->where('writer_payout_id', $payout->id)
                    ->update(['writer_payout_id' => null]);
            }

            // §41: payout processed, either way, with the office's note.
            $writerUserId = WriterProfile::query()->whereKey($payout->writer_id)->value('user_id');
            if ($writerUserId) {
                app(NotifyLibraryUserAction::class)->execute(
                    (int) $writerUserId,
                    $paid ? 'Payout paid' : 'Payout not paid',
                    'MVR '.number_format((float) $payout->amount, 2).($paid ? ' has been paid out.' : ' was not paid out; the earnings are available again.').($note !== null && trim($note) !== '' ? ' '.trim($note) : ''),
                    '/write',
                    'payout_decided',
                );
            }

            return $payout->refresh();
        });
    }
}
