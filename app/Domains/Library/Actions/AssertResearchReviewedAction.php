<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryReviewAssignment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * RESEARCH_ARTICLES_PLAN R3 (the owner's decision D2, 2026-09-29: "peer
 * review is a must"). The ONE place that decides whether a research item
 * has been reviewed enough to publish, and what its review looks like to a
 * person.
 *
 * Reviewed enough means: reviewer accepts **in the item's current review
 * round** number at least the required count (D5 — the office setting
 * `research_reviews_required`, never below 1). An accept from an earlier
 * round does not count: the reviewer accepted a text that has since been
 * revised.
 *
 * Called by `PublishLibraryItemAction` — every publish path goes through it,
 * the office's Publish button, the editor's approve decision and anything
 * later — and by `ReviewLibraryItemSubmissionAction` before it tries. The
 * only way past it is R2's import of the website's already-public papers,
 * which does not publish through the publisher at all.
 */
class AssertResearchReviewedAction
{
    public function execute(LibraryItem $item): void
    {
        if ($item->content_type !== LibraryContentType::Research) {
            return;
        }

        $required = $this->required();
        $accepts = $this->acceptsInRound($item);
        if ($accepts < $required) {
            throw ValidationException::withMessages([
                'item' => $required === 1
                    ? __('admin.library_office_error_research_gate_one')
                    : __('admin.library_office_error_research_gate_many', ['required' => $required, 'accepts' => $accepts]),
            ]);
        }
    }

    /** The office's setting, and never less than one. */
    public function required(): int
    {
        return max(1, (int) app(ResolveLibrarySettingAction::class)->execute('research_reviews_required'));
    }

    public function acceptsInRound(LibraryItem $item, ?Collection $assignments = null): int
    {
        $round = max(1, (int) $item->review_round);
        $assignments ??= LibraryReviewAssignment::query()->where('library_item_id', $item->id)->get();

        return $assignments
            ->filter(fn (LibraryReviewAssignment $a) => $a->status === 'done' && $a->recommendation === 'accept' && (int) $a->round === $round)
            ->count();
    }

    /**
     * Where a research item's review stands, in words a writer and the
     * office both read (plan R3, "status the humans can read"). Null for an
     * item that is not research, or not in review.
     *
     * @return array{state: string, accepts: int, required: int, round: int}|null
     */
    public function state(LibraryItem $item, ?Collection $assignments = null): ?array
    {
        if ($item->content_type !== LibraryContentType::Research) {
            return null;
        }
        $status = $item->status instanceof LibraryItemStatus ? $item->status : LibraryItemStatus::tryFrom((string) $item->status);
        $assignments ??= LibraryReviewAssignment::query()->where('library_item_id', $item->id)->get();
        $round = max(1, (int) $item->review_round);
        $required = $this->required();
        $accepts = $this->acceptsInRound($item, $assignments);
        $inRound = $assignments->filter(fn (LibraryReviewAssignment $a) => (int) $a->round === $round);

        $state = match (true) {
            $status === LibraryItemStatus::Rejected => 'rejected',
            $status === LibraryItemStatus::ChangesRequested && $inRound->contains(fn ($a) => $a->recommendation === 'revise') => 'revision_requested',
            $status !== LibraryItemStatus::Submitted => null,
            $accepts >= $required => 'accepted_awaiting_publish',
            $inRound->isEmpty() => 'awaiting_reviewer',
            default => 'with_reviewer',
        };

        return $state === null ? null : ['state' => $state, 'accepts' => $accepts, 'required' => $required, 'round' => $round];
    }

    /**
     * The same, for many items at once (the office's lists).
     *
     * @param  Collection<int, LibraryItem>  $items
     * @return array<int, array{state: string, accepts: int, required: int, round: int}|null>
     */
    public function states(Collection $items): array
    {
        $research = $items->filter(fn (LibraryItem $item) => $item->content_type === LibraryContentType::Research);
        $assignments = LibraryReviewAssignment::query()
            ->whereIn('library_item_id', $research->pluck('id'))
            ->get()
            ->groupBy('library_item_id');

        $out = [];
        foreach ($items as $item) {
            $out[$item->id] = $this->state($item, $assignments->get($item->id) ?? collect());
        }

        return $out;
    }
}
