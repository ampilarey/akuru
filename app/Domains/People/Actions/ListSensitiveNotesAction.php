<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\SensitiveNoteView;
use App\Domains\People\Models\StudentSensitiveNote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read a child's sensitive notes — **and record that you did**.
 *
 * The logging is not a side effect bolted on: it is why this action exists
 * rather than a query in a controller. Whichever way the Institute's policy
 * lands on who may read health and welfare information, *"who looked at my
 * child's record, and when?"* is the question that will be asked, and it is
 * unanswerable retrospectively.
 *
 * `$viewerId` is therefore required. There is no way to call this without
 * leaving a trace, which is the point.
 */
class ListSensitiveNotesAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $studentId, int $viewerId, bool $includeArchived = false): Collection
    {
        $notes = StudentSensitiveNote::query()
            ->where('student_id', $studentId)
            ->when(! $includeArchived, fn ($query) => $query->inUse())
            ->orderByDesc('created_at')
            ->get();

        // Logged even when there is nothing to see: "somebody went looking"
        // is itself worth knowing.
        SensitiveNoteView::query()->create([
            'student_id' => $studentId,
            'viewed_by' => $viewerId,
            'viewed_at' => now(),
        ]);

        if ($notes->isEmpty()) {
            return collect();
        }

        $authors = DB::table('users')
            ->whereIn('id', $notes->pluck('author_id')->filter()->all())
            ->pluck('name', 'id');

        return $notes->map(fn (StudentSensitiveNote $note): array => [
            'id' => (int) $note->id,
            'category' => $note->category->value,
            'category_label' => $note->category->label(),
            'summary' => $note->summary,
            'body' => $note->body,
            'review_on' => $note->review_on?->toDateString(),
            'needs_review' => $note->review_on !== null && $note->review_on->isPast(),
            'author' => $authors->get((int) $note->author_id) ?? 'Unknown',
            'recorded_on' => $note->created_at?->toDateString(),
            'archived' => $note->archived_at !== null,
        ])->values();
    }
}
