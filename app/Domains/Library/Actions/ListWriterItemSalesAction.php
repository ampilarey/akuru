<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterProfile;

/**
 * Per-book sales for the writer portal (§11): copies sold, gross value,
 * and the writer's own share per item, with refunds broken out. Built
 * from writer_earnings so "earned" matches the payout ledger exactly.
 */
class ListWriterItemSalesAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $userId): array
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->first();
        if ($profile === null) {
            return [];
        }

        $rows = WriterEarning::query()
            ->where('writer_id', $profile->id)
            ->selectRaw("library_item_id,
                SUM(CASE WHEN status <> 'refunded' THEN 1 ELSE 0 END) as sold,
                SUM(CASE WHEN status <> 'refunded' THEN gross_amount ELSE 0 END) as gross,
                SUM(CASE WHEN status <> 'refunded' THEN writer_amount ELSE 0 END) as earned,
                SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) as refunded")
            ->groupBy('library_item_id')
            ->get();

        $titles = LibraryItem::query()
            ->whereIn('id', $rows->pluck('library_item_id'))
            ->pluck('title', 'id');

        return $rows
            ->map(fn ($row) => [
                'item_id' => (int) $row->library_item_id,
                'title' => (string) ($titles[$row->library_item_id] ?? 'Item #'.$row->library_item_id),
                'sold' => (int) $row->sold,
                'gross' => round((float) $row->gross, 2),
                'earned' => round((float) $row->earned, 2),
                'refunded' => (int) $row->refunded,
            ])
            ->sortByDesc('earned')
            ->values()
            ->all();
    }
}
