<?php

namespace App\Domains\Library\Actions;

use App\Domains\Identity\Actions\IdentityVerificationAction;
use App\Domains\Library\Models\WriterBankDetail;
use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterPayout;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * L6 (§23, §9.4 gate): a writer asks for their AVAILABLE balance. Blocked
 * until the operator confirms tax/accounting treatment
 * (library.payouts_enabled); needs bank details; respects the minimum;
 * earnings are linked to the payout so double-requests cannot happen.
 */
class RequestWriterPayoutAction
{
    public function execute(int $userId): WriterPayout
    {
        if (! app(\App\Domains\Library\Actions\ResolveLibrarySettingAction::class)->execute('payouts_enabled')) {
            throw ValidationException::withMessages([
                'payout' => 'Payouts are not open yet — your earnings keep accruing and stay yours.',
            ]);
        }

        if (! app(IdentityVerificationAction::class)->isVerified($userId, 'writer')) {
            throw ValidationException::withMessages(['payout' => __('account.id_needed_writer')]);
        }
        $profile = WriterProfile::query()->where('user_id', $userId)->where('status', 'active')->first();
        if ($profile === null) {
            throw ValidationException::withMessages(['writer' => 'An approved writer profile is required.']);
        }
        if (! WriterBankDetail::query()->where('writer_id', $profile->id)->exists()) {
            throw ValidationException::withMessages(['payout' => 'Add your bank details before requesting a payout.']);
        }

        return DB::transaction(function () use ($profile) {
            app(MatureWriterEarningsAction::class)->execute($profile->id);

            $earnings = WriterEarning::query()
                ->where('writer_id', $profile->id)
                ->where('status', 'available')
                ->whereNull('writer_payout_id')
                ->lockForUpdate()
                ->get();
            $amount = round((float) $earnings->sum('writer_amount'), 2);
            $minimum = (float) app(\App\Domains\Library\Actions\ResolveLibrarySettingAction::class)->execute('min_payout');
            if ($amount < $minimum) {
                throw ValidationException::withMessages([
                    'payout' => "Available balance ({$amount}) is below the minimum payout ({$minimum}).",
                ]);
            }

            $payout = WriterPayout::query()->create([
                'writer_id' => $profile->id,
                'amount' => $amount,
                'currency' => 'MVR',
                'status' => 'requested',
                'requested_at' => now(),
            ]);
            WriterEarning::query()
                ->whereIn('id', $earnings->pluck('id'))
                ->update(['writer_payout_id' => $payout->id]);

            return $payout;
        });
    }
}
