<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Models\OfferingRepinEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListOfferingRepinEventsAction
{
    /**
     * SPEC §28.4's record, read back. An audit trail that is written but never
     * shown answers nobody's question.
     *
     * The pin maps themselves are not sent — they are large and a reader wants
     * the shape of the change, not every lesson id. How many lessons moved,
     * and which, is what distinguishes a routine re-pin from one that rewrote
     * the whole course under enrolled students.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $offeringId): Collection
    {
        $rows = OfferingRepinEvent::query()
            ->where('course_offering_id', $offeringId)
            ->orderByDesc('id')
            ->get();

        $names = DB::table('users')
            ->whereIn('id', $rows->pluck('changed_by')->filter()->unique())
            ->pluck('name', 'id');

        return $rows->map(fn (OfferingRepinEvent $event): array => [
            'id' => $event->id,
            'changed_at' => $event->created_at?->toIso8601String(),
            'changed_by' => $event->changed_by ? ($names[$event->changed_by] ?? null) : null,
            'old_pin_mode' => $event->old_pin_mode,
            'new_pin_mode' => $event->new_pin_mode,
            'reason' => $event->reason,
            'lessons_before' => count($event->old_pinned_revision_json ?? []),
            'lessons_after' => count($event->new_pinned_revision_json ?? []),
            'changed_lessons' => $this->changedLessons(
                $event->old_pinned_revision_json ?? [],
                $event->new_pinned_revision_json ?? [],
            ),
        ])->values();
    }

    /**
     * Lesson ids whose pinned revision actually moved, plus ones that appeared
     * or disappeared. A re-pin that changes nothing should read as changing
     * nothing rather than as a wall of identical numbers.
     *
     * @param  array<array-key, mixed>  $old
     * @param  array<array-key, mixed>  $new
     * @return list<int>
     */
    private function changedLessons(array $old, array $new): array
    {
        // Both sides are `lesson id => revision id` from the same JSON cast, so
        // they are directly comparable: PHP casts numeric string keys back to
        // int on decode, and the values come back as ints. No normalising pass
        // is needed — an earlier draft had one, and removing it changed no
        // test, which is how I know it was decoration rather than defence.
        $ids = array_unique(array_merge(array_keys($old), array_keys($new)));
        sort($ids);

        return array_values(array_filter(
            $ids,
            fn ($id): bool => ($old[$id] ?? null) !== ($new[$id] ?? null),
        ));
    }
}
