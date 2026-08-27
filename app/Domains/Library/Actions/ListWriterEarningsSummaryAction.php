<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\WriterBankDetail;
use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterProfile;

/**
 * L6 (§11.2): the writer's money view — totals per lifecycle state and
 * whether a payout can be requested. Matures due earnings first.
 */
class ListWriterEarningsSummaryAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(int $userId): ?array
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->first();
        if ($profile === null) {
            return null;
        }

        app(MatureWriterEarningsAction::class)->execute($profile->id);

        $totals = WriterEarning::query()
            ->where('writer_id', $profile->id)
            ->selectRaw('status, SUM(writer_amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $available = round((float) ($totals['available'] ?? 0), 2);

        return [
            'pending' => round((float) ($totals['pending'] ?? 0), 2),
            'available' => $available,
            'paid' => round((float) ($totals['paid'] ?? 0), 2),
            'refunded' => round((float) ($totals['refunded'] ?? 0), 2),
            'payouts_enabled' => (bool) config('library.payouts_enabled'),
            'min_payout' => (float) config('library.min_payout', 100),
            'has_bank_details' => WriterBankDetail::query()->where('writer_id', $profile->id)->exists(),
            'can_request' => (bool) config('library.payouts_enabled')
                && $available >= (float) config('library.min_payout', 100),
        ];
    }
}
