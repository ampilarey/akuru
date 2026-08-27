<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemReview;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Library\Models\WriterApplication;
use App\Domains\Library\Models\WriterProfile;

/**
 * L5 (§11): the writer's own world — application state, own items with
 * their editorial status and the latest editor comment, and basic sales
 * (aggregates only, §43.9). Nothing here crosses into other writers.
 */
class ListWriterDashboardAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $userId): array
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->first();
        $application = WriterApplication::query()
            ->where('user_id', $userId)
            ->latest()
            ->first();

        $items = [];
        $sales = [];
        if ($profile !== null) {
            $itemModels = LibraryItem::query()
                ->where('writer_id', $profile->id)
                ->orderByDesc('updated_at')
                ->get();

            $latestComments = LibraryItemReview::query()
                ->whereIn('library_item_id', $itemModels->pluck('id'))
                ->whereNotNull('comment')
                ->orderByDesc('id')
                ->get()
                ->unique('library_item_id')
                ->keyBy('library_item_id');

            $salesRows = LibraryPurchase::query()
                ->whereIn('library_item_id', $itemModels->pluck('id'))
                ->where('status', 'paid')
                ->selectRaw('library_item_id, COUNT(*) as sales, SUM(amount) as revenue')
                ->groupBy('library_item_id')
                ->get()
                ->keyBy('library_item_id');

            $items = $itemModels->map(fn (LibraryItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'content_type' => $item->content_type?->value,
                'access_type' => $item->access_type?->value,
                'price' => $item->price !== null ? (float) $item->price : null,
                'abstract' => $item->abstract,
                'body' => $item->body,
                'status' => $item->status?->value,
                'submitted_at' => $item->submitted_at?->toDateTimeString(),
                'published_at' => $item->published_at?->toDateTimeString(),
                'latest_comment' => $latestComments[$item->id]?->comment,
                'sales' => (int) ($salesRows[$item->id]->sales ?? 0),
                'revenue' => (float) ($salesRows[$item->id]->revenue ?? 0),
            ])->values()->all();

            $sales = [
                'total_sales' => (int) $salesRows->sum('sales'),
                'total_revenue' => (float) $salesRows->sum('revenue'),
            ];
        }

        return [
            'profile' => $profile ? [
                'display_name' => $profile->display_name,
                'status' => $profile->status,
                'approved_at' => $profile->approved_at?->toDateString(),
            ] : null,
            'application' => $application ? [
                'status' => $application->status,
                'decision_note' => $application->decision_note,
                'created_at' => $application->created_at?->toDateString(),
            ] : null,
            'items' => $items,
            'sales' => $sales,
        ];
    }
}
