<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemReview;
use App\Domains\Library\Models\LibraryReviewAssignment;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * L5: a writer hands their own draft (or change-requested revision) to the
 * editorial queue. Logged in the append-only review trail.
 */
class SubmitLibraryItemForReviewAction
{
    public function execute(int $userId, int $itemId, ?string $note = null): LibraryItem
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->where('status', 'active')->first();
        if ($profile === null) {
            throw ValidationException::withMessages(['writer' => 'An approved writer profile is required.']);
        }

        $item = LibraryItem::query()->findOrFail($itemId);
        if ((int) $item->writer_id !== (int) $profile->id) {
            throw ValidationException::withMessages(['item' => 'You can only submit your own items.']);
        }

        $status = $item->status instanceof LibraryItemStatus ? $item->status : LibraryItemStatus::tryFrom((string) $item->status);
        if (! in_array($status, [LibraryItemStatus::Draft, LibraryItemStatus::ChangesRequested], true)) {
            throw ValidationException::withMessages(['item' => 'Only drafts and change-requested items can be submitted.']);
        }

        // §11.3 / §11.5: no submission without the declarations. The
        // copyright declaration for everything; research adds originality
        // and conflict of interest. The form saves them with the draft, so
        // this is the writer being asked once, not a hurdle at the end.
        $declared = is_array($item->declarations) ? $item->declarations : [];
        $required = ['copyright'];
        if ($item->content_type === LibraryContentType::Research) {
            $required[] = 'originality';
            $required[] = 'conflict_of_interest';
        }
        $missing = array_values(array_filter($required, fn (string $name) => empty($declared[$name])));
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'declarations' => 'Before submitting, confirm the '.implode(', ', array_map(fn ($n) => str_replace('_', ' ', $n), $missing)).' declaration'.(count($missing) === 1 ? '' : 's').' on the draft.',
            ]);
        }

        $item->status = LibraryItemStatus::Submitted;
        $item->submitted_at = now();
        // R3: research coming back after changes starts a new review round.
        $reopened = collect();
        if ($status === LibraryItemStatus::ChangesRequested && $item->content_type === LibraryContentType::Research) {
            $item->review_round = max(1, (int) $item->review_round) + 1;
            $reopened = $this->openRound($item);
        }
        $item->save();

        LibraryItemReview::query()->create([
            'library_item_id' => $item->id,
            'reviewer_user_id' => null,
            'decision' => 'submitted',
            // R3b: on a resubmission, what the writer changed — the
            // reviewers read it beside the revised text.
            'comment' => $note !== null && trim($note) !== '' ? trim($note) : null,
        ]);

        // §41: the writer hears it arrived; the office hears there is work.
        $notify = app(NotifyLibraryUserAction::class);
        $notify->execute($userId, 'Submission received', '"'.$item->title.'" is in the editorial queue. You will hear when it is reviewed.', '/write');
        $notify->office('New library submission', $profile->display_name.' submitted "'.$item->title.'" for review.', '/admin/library');
        foreach ($reopened as $reviewerId) {
            $notify->execute((int) $reviewerId, 'Revised research to review', '"'.$item->title.'" is back from the writer for round '.$item->review_round.'.', '/review');
        }

        return $item->refresh();
    }

    /**
     * R3 (plan, "revision loop"): every reviewer who asked for revisions is
     * asked again, in the new round; a reviewer who had not reported yet
     * reads the revised text too. An earlier accept stays in its own round
     * and no longer counts — the office may assign that reviewer again.
     *
     * @return Collection<int, int> the reviewers to tell
     */
    private function openRound(LibraryItem $item): Collection
    {
        $reviewers = collect();
        foreach (LibraryReviewAssignment::query()->where('library_item_id', $item->id)->get() as $assignment) {
            $revise = $assignment->status === 'done' && $assignment->recommendation === 'revise';
            if ($revise || $assignment->status === 'assigned') {
                $assignment->fill([
                    'status' => 'assigned', 'recommendation' => null, 'round' => $item->review_round,
                    // R3b: a new round, a new due date and fresh reminders.
                    'due_at' => now()->addDays(LibraryReviewAssignment::DEFAULT_DUE_DAYS)->endOfDay(), 'reminded_at' => null,
                ])->save();
                $reviewers->push((int) $assignment->reviewer_user_id);
            }
        }

        return $reviewers;
    }
}
