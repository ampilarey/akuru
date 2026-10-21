<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Actions\ResolveLibrarySettingAction;
use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\WriterEarning;
use App\Support\Said;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL7 (§7.2, D12): the office marks a published post *Featured
 * this month*, and may reward it.
 *
 *  - A reward is a `writer_earnings` row of kind `reward`, funded by Akuru:
 *    no payment, no share kept. It is off unless the office turned it on
 *    (`reward_enabled`), at most `reward_cap`, and once per post.
 *  - It matures like any earning and, for a student, is paid as their other
 *    earnings are (BL6b).
 */
class FeaturePostAction
{
    public function execute(int $itemId, int $officeUserId, bool $feature, ?float $reward = null): LibraryItem
    {
        $item = LibraryItem::query()->with('writer')->findOrFail($itemId);
        if ($item->content_type !== LibraryContentType::Post || $item->status !== LibraryItemStatus::Published || $item->writer === null) {
            throw ValidationException::withMessages(['feature' => __('blog.error_feature_post')]);
        }
        $settings = app(ResolveLibrarySettingAction::class);
        if ($reward !== null && $reward > 0) {
            if (! $feature || ! $settings->execute('reward_enabled')) {
                throw ValidationException::withMessages(['reward' => __('blog.error_reward_off')]);
            }
            if ($reward > (int) $settings->execute('reward_cap')) {
                throw ValidationException::withMessages(['reward' => __('blog.error_reward_cap', ['cap' => (int) $settings->execute('reward_cap')])]);
            }
            if (WriterEarning::query()->where('kind', 'reward')->where('library_item_id', $item->id)->exists()) {
                throw ValidationException::withMessages(['reward' => __('blog.error_reward_once')]);
            }
        }

        DB::transaction(function () use ($item, $feature, $reward, $settings) {
            $item->forceFill(['featured' => $feature, 'featured_at' => $feature ? now() : null])->save();
            if ($feature && $reward !== null && $reward > 0) {
                WriterEarning::query()->create([
                    'writer_id' => $item->writer_id,
                    'kind' => 'reward',
                    'library_item_id' => $item->id,
                    'gross_amount' => round($reward, 2),
                    'discount_amount' => 0,
                    'wallet_amount' => 0,
                    'bml_amount' => 0,
                    'platform_commission' => 0,
                    'writer_amount' => round($reward, 2),
                    'status' => 'pending',
                    'available_at' => now()->addDays((int) $settings->execute('refund_window_days')),
                ]);
            }
        });

        if ($feature) {
            app(NotifyLibraryUserAction::class)->execute(
                (int) $item->writer->user_id,
                Said::of('library_notices.featured_title'),
                Said::join([
                    Said::of('library_notices.featured_body', ['title' => $item->title]),
                    $reward ? Said::of('library_notices.featured_reward', ['amount' => number_format($reward, 2)]) : null,
                ]),
                '/library/'.$item->slug,
            );
        }

        return $item->refresh();
    }
}
