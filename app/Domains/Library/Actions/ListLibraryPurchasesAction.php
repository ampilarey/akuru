<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryPurchase;

/**
 * L3: a reader's purchase history, and the admin sales rollup
 * (LIBRARY_PLAN §37 MVP: purchase history + sales report).
 */
class ListLibraryPurchasesAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $userId): array
    {
        return LibraryPurchase::query()
            ->with('item')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (LibraryPurchase $row): array => [
                'id' => $row->id,
                'title' => $row->item?->title,
                'slug' => $row->item?->slug,
                'amount' => (string) $row->amount,
                'currency' => $row->currency,
                'status' => $row->status,
                'purchased_at' => $row->purchased_at?->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function salesSummary(): array
    {
        return LibraryPurchase::query()
            ->where('status', 'paid')
            ->selectRaw('library_item_id, count(*) as sales, sum(amount) as revenue')
            ->groupBy('library_item_id')
            ->orderByDesc('revenue')
            ->with('item')
            ->get()
            ->map(fn ($row) => [
                'library_item_id' => (int) $row->library_item_id,
                'title' => $row->item?->title,
                'sales' => (int) $row->sales,
                'revenue' => (string) $row->revenue,
            ])
            ->values()
            ->all();
    }
}
