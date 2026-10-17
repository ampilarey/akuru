<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Commerce\Actions\DebitWalletAction;
use App\Domains\Finance\Actions\InitiatePayablePaymentAction;
use App\Domains\Library\Actions\ResolveLibrarySettingAction;
use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryTip;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL6a (§3.6, §7.1): a signed-in reader tips a blog's writer.
 *
 *  - Only on a blog whose writer turned tips on, never to oneself, and only
 *    one of the office's preset amounts. A tip is never discounted.
 *  - By card: a `pending` tip and a BML payment through Finance's payable
 *    initiation; the tip is paid, and earns, only on the **webhook**
 *    (RecordTipPaidAction), never on the return URL (rule 12).
 *  - From the wallet (which holds what a gift card added): internal money,
 *    so the debit, the paid tip and its earning are written at once.
 *  - The optional thank-you note waits for the office, like a comment.
 *
 * @return array{tip: LibraryTip, redirect_url: ?string, error: ?string}
 */
class StartTipAction
{
    public function execute(int $userId, string $writerSlug, float $amount, ?int $itemId = null, ?string $note = null, bool $payWithWallet = false, ?string $returnUrl = null): array
    {
        $writer = WriterProfile::query()->where('slug', $writerSlug)->where('status', 'active')->first();
        if ($writer === null || ! $writer->tips_enabled) {
            throw ValidationException::withMessages(['tip' => __('blog.error_tips_closed')]);
        }
        if ((int) $writer->user_id === $userId) {
            throw ValidationException::withMessages(['tip' => __('blog.error_tip_self')]);
        }
        if (! in_array((int) round($amount), self::amounts(), true) || round($amount) != $amount) {
            throw ValidationException::withMessages(['amount' => __('blog.error_tip_amount')]);
        }
        $item = $itemId === null ? null : LibraryItem::query()->whereKey($itemId)->where('writer_id', $writer->id)->where('content_type', LibraryContentType::Post->value)->where('status', 'published')->first();
        $text = trim(strip_tags((string) $note));
        $note = $text === '' ? null : mb_substr($text, 0, 300);

        $fields = [
            'user_id' => $userId, 'writer_id' => $writer->id, 'library_item_id' => $item?->id, 'amount' => round($amount, 2), 'currency' => 'MVR',
            'note' => $note, 'note_status' => $note === null ? 'none' : 'pending',
        ];

        if ($payWithWallet) {
            $tip = DB::transaction(function () use ($fields, $userId, $amount, $writer) {
                $tip = LibraryTip::query()->create($fields + ['method' => 'wallet', 'status' => 'pending']);
                app(DebitWalletAction::class)->execute($userId, round($amount, 2), 'library_tip', $tip->id, 'Tip to '.$writer->display_name);

                return $tip;
            });
            app(RecordTipPaidAction::class)->execute($tip->id);

            return ['tip' => $tip->refresh(), 'redirect_url' => null, 'error' => null];
        }

        $tip = LibraryTip::query()->create($fields + ['method' => 'card', 'status' => 'pending']);
        $initiated = app(InitiatePayablePaymentAction::class)->execute('library_tip', $tip->id, $userId, round($amount, 2), 'MVR', $returnUrl);
        $tip->fill(['payment_id' => $initiated['payment']->id] + ($initiated['redirect_url'] === null ? ['status' => 'failed'] : []))->save();

        return ['tip' => $tip->refresh(), 'redirect_url' => $initiated['redirect_url'], 'error' => $initiated['error']];
    }

    /** @return list<int> the office's preset amounts, in MVR */
    public static function amounts(): array
    {
        $list = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) app(ResolveLibrarySettingAction::class)->execute('tip_amounts'))), fn (int $a) => $a > 0)));
        sort($list);

        return $list === [] ? [5, 10, 25, 50] : $list;
    }
}
