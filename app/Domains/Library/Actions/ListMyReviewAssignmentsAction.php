<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryReviewAssignment;

/**
 * L7: the reviewer's inbox — their own assignments only, with the item's
 * text to review. Reviewers see nothing about sales or other reviewers.
 */
class ListMyReviewAssignmentsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $userId): array
    {
        $assignments = LibraryReviewAssignment::query()
            ->where('reviewer_user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        $items = LibraryItem::query()
            ->whereIn('id', $assignments->pluck('library_item_id'))
            ->get()
            ->keyBy('id');

        return $assignments->map(function (LibraryReviewAssignment $assignment) use ($items) {
            $item = $items->get($assignment->library_item_id);

            return [
                'id' => $assignment->id,
                'status' => $assignment->status,
                'recommendation' => $assignment->recommendation,
                'assigned_at' => $assignment->created_at?->toDateString(),
                'item' => $item === null ? null : [
                    'title' => $item->title,
                    'abstract' => $item->abstract,
                    'body' => $item->body,
                    'citations' => $item->citations,
                    'status' => $item->status?->value,
                ],
            ];
        })->values()->all();
    }
}
