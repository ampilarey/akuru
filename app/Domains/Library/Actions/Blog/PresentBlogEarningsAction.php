<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\MatureWriterEarningsAction;
use App\Domains\Library\Actions\ResolveLibrarySettingAction;
use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterPayout;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL6b (§7.3, §7.4): a student blogger's earnings on *My blog* —
 * waiting, available and paid — and whether they may ask for a payout now,
 * and to whose wallet. Never a bank, never a price.
 */
class PresentBlogEarningsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(WriterProfile $profile): array
    {
        app(MatureWriterEarningsAction::class)->execute($profile->id);
        $sum = fn (string $status) => round((float) WriterEarning::query()->where('writer_id', $profile->id)->where('status', $status)->sum('writer_amount'), 2);
        $available = round((float) WriterEarning::query()->where('writer_id', $profile->id)->where('status', 'available')->whereNull('writer_payout_id')->sum('writer_amount'), 2);
        $settings = app(ResolveLibrarySettingAction::class);

        try {
            $target = app(ResolveBlogPayoutTargetAction::class)->execute($profile);
            $problem = null;
        } catch (ValidationException $e) {
            $target = null;
            $problem = collect($e->errors())->flatten()->first();
        }

        return [
            'pending' => $sum('pending'),
            'available' => $available,
            'paid' => $sum('paid'),
            'payouts_enabled' => (bool) $settings->execute('payouts_enabled'),
            'minimum' => (int) $settings->execute('min_payout'),
            'to_parent' => (bool) ($target['to_parent'] ?? false),
            'problem' => $problem,
            'requested' => WriterPayout::query()->where('writer_id', $profile->id)->where('status', 'requested')->exists(),
        ];
    }
}
