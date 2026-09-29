<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemReview;
use App\Domains\Library\Models\LibraryReviewAssignment;

/**
 * L7: the reviewer's inbox — their own assignments only, with the item's
 * text to review. Reviewers see nothing about the author, the price, sales
 * or the other reviewers (§43.8, RESEARCH_ARTICLES_PLAN D6: single-blind).
 *
 * R3b adds what a reviewer needs to do the job: the round, the due date,
 * the conflict-of-interest step (the text is withheld until it is
 * declared), their own earlier reports on this item, and — on a revision —
 * the writer's note on what changed.
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
        $trail = LibraryItemReview::query()
            ->whereIn('library_item_id', $assignments->pluck('library_item_id'))
            ->orderBy('id')
            ->get()
            ->groupBy('library_item_id');

        return $assignments->map(function (LibraryReviewAssignment $assignment) use ($items, $trail, $userId) {
            $item = $items->get($assignment->library_item_id);
            $history = $trail->get($assignment->library_item_id) ?? collect();
            $declared = $assignment->coi_declared_at !== null;
            $open = $assignment->status === 'assigned';

            return [
                'id' => $assignment->id,
                'status' => $assignment->status,
                'recommendation' => $assignment->recommendation,
                // R3: which review round this is (2 or more means a revision).
                'round' => (int) $assignment->round,
                'assigned_at' => $assignment->created_at?->toDateString(),
                'due_on' => $assignment->due_at?->timezone('Indian/Maldives')->toDateString(),
                'overdue' => $open && $assignment->due_at !== null && $assignment->due_at->isPast(),
                'coi_declared' => $declared,
                // Their own reports on this item, oldest first — never anyone else's.
                'my_reports' => $history
                    ->filter(fn (LibraryItemReview $row) => (int) $row->reviewer_user_id === $userId && str_starts_with((string) $row->decision, 'reviewer_'))
                    ->map(fn (LibraryItemReview $row) => [
                        'recommendation' => substr((string) $row->decision, strlen('reviewer_')),
                        'comment' => $row->comment,
                        'at' => $row->created_at?->toDateString(),
                    ])->values()->all(),
                // The writer's note on their latest resubmission, on a revision.
                'revision_note' => (int) $assignment->round > 1
                    ? $history->filter(fn (LibraryItemReview $row) => $row->decision === 'submitted' && $row->comment !== null)->last()?->comment
                    : null,
                'item' => $item === null ? null : [
                    'title' => $item->title,
                    'status' => $item->status?->value,
                    // Withheld until the reviewer declares no conflict.
                    'abstract' => $declared ? $item->abstract : null,
                    'body' => $declared ? $item->body : null,
                    'citations' => $declared ? $item->citations : null,
                ],
            ];
        })->values()->all();
    }
}
