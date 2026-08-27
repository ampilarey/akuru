<?php

namespace App\Domains\Library\Actions;

use App\Domains\Commerce\Actions\ResolveRedemptionFundingSourceAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterProfile;

/**
 * L6 (§21–§23): one earning per PAID sale of a writer's item — called from
 * the webhook grant listener AND the wallet checkout branch, idempotent on
 * the purchase. Commission resolution: per-item override → writer default
 * → platform default (§22). Funding models (§21):
 *   A shared (default) — writer % of the PAID amount;
 *   B akuru-funded     — writer % of the ORIGINAL price;
 *   C writer-funded    — writer absorbs: paid − Akuru's % of the original.
 * Wallet is PAYMENT, not discount (§16.2) — a wallet sale earns from the
 * full paid value. available_at = purchase + refund window (§24/§43.7).
 */
class RecordWriterEarningForPurchaseAction
{
    public function execute(int $purchaseId): ?WriterEarning
    {
        $purchase = LibraryPurchase::query()->find($purchaseId);
        if ($purchase === null || $purchase->status !== 'paid') {
            return null;
        }
        if (WriterEarning::query()->where('library_purchase_id', $purchase->id)->exists()) {
            return WriterEarning::query()->where('library_purchase_id', $purchase->id)->first();
        }

        $item = LibraryItem::query()->find($purchase->library_item_id);
        if ($item === null || $item->writer_id === null) {
            return null; // house content — no writer share
        }
        $writer = WriterProfile::query()->find($item->writer_id);
        if ($writer === null) {
            return null;
        }

        $original = round((float) ($item->price ?? 0), 2);
        $paid = round((float) $purchase->amount, 2);

        $redemption = app(ResolveRedemptionFundingSourceAction::class)
            ->execute('library_purchase', $purchase->id);
        $discountAmount = $redemption['amount_discounted'] ?? round(max($original - $paid, 0), 2);
        $fundingSource = $redemption['funding_source'] ?? null;

        $writerPercent = $this->writerPercent($item, $writer);
        $writerAmount = match ($fundingSource) {
            'akuru' => round($original * $writerPercent / 100, 2),
            'writer' => round(max($paid - $original * (100 - $writerPercent) / 100, 0), 2),
            default => round($paid * $writerPercent / 100, 2),
        };

        return WriterEarning::query()->create([
            'writer_id' => $writer->id,
            'library_item_id' => $item->id,
            'library_purchase_id' => $purchase->id,
            'gross_amount' => $original,
            'discount_amount' => round((float) $discountAmount, 2),
            'discount_funding_source' => $fundingSource,
            'wallet_amount' => $purchase->payment_id === null ? $paid : 0,
            'bml_amount' => $purchase->payment_id !== null ? $paid : 0,
            'platform_commission' => round($paid - $writerAmount, 2),
            'writer_amount' => $writerAmount,
            'status' => 'pending',
            'available_at' => ($purchase->purchased_at ?? now())
                ->copy()
                ->addDays((int) config('library.refund_window_days', 7)),
        ]);
    }

    private function writerPercent(LibraryItem $item, WriterProfile $writer): float
    {
        if ($item->commission_type === 'percentage' && $item->commission_value !== null) {
            return (float) $item->commission_value;
        }
        if ($writer->default_commission !== null) {
            return (float) $writer->default_commission;
        }

        return (float) config('library.default_writer_commission', 70);
    }
}
