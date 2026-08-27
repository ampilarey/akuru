<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemReview;
use App\Domains\Library\Models\LibraryReviewAssignment;
use App\Domains\Library\Models\WriterApplication;
use App\Domains\Library\Models\WriterProfile;

/**
 * L5 admin queues: pending writer applications and submitted items,
 * oldest first — the editorial inbox.
 */
class ListWriterQueuesAction
{
    /**
     * @return array{applications: list<array<string, mixed>>, submissions: list<array<string, mixed>>}
     */
    public function execute(): array
    {
        $applications = WriterApplication::query()
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(fn (WriterApplication $application) => [
                'id' => $application->id,
                'display_name' => $application->display_name,
                'bio' => $application->bio,
                'qualifications' => $application->qualifications,
                'expertise' => $application->expertise,
                'motivation' => $application->motivation,
                'applied_at' => $application->created_at?->toDateString(),
            ])->values()->all();

        $submitted = LibraryItem::query()
            ->where('status', LibraryItemStatus::Submitted)
            ->orderBy('submitted_at')
            ->get();

        $writers = WriterProfile::query()
            ->whereIn('id', $submitted->pluck('writer_id')->filter())
            ->get()
            ->keyBy('id');
        $history = LibraryItemReview::query()
            ->whereIn('library_item_id', $submitted->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('library_item_id');
        // L7: peer-review state per research item.
        $assignments = LibraryReviewAssignment::query()
            ->whereIn('library_item_id', $submitted->pluck('id'))
            ->get()
            ->groupBy('library_item_id');

        $submissions = $submitted->map(fn (LibraryItem $item) => [
            'id' => $item->id,
            'title' => $item->title,
            'slug' => $item->slug,
            'content_type' => $item->content_type?->value,
            'access_type' => $item->access_type?->value,
            'price' => $item->price !== null ? (float) $item->price : null,
            'writer' => $writers->get($item->writer_id)?->display_name ?? '—',
            'submitted_at' => $item->submitted_at?->toDateTimeString(),
            'reviews' => ($assignments->get($item->id) ?? collect())->map(fn (LibraryReviewAssignment $assignment) => [
                'status' => $assignment->status,
                'recommendation' => $assignment->recommendation,
            ])->values()->all(),
            'history' => ($history->get($item->id) ?? collect())->map(fn (LibraryItemReview $review) => [
                'decision' => $review->decision,
                'comment' => $review->comment,
                'at' => $review->created_at?->toDateTimeString(),
            ])->values()->all(),
        ])->values()->all();

        return ['applications' => $applications, 'submissions' => $submissions];
    }
}
