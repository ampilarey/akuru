<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryReviewAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * L7 (§12.2): the editor hands a SUBMITTED research item to a peer
 * reviewer (any user, picked by email — reviewer is a role on the
 * unified identity). One assignment per reviewer per item.
 */
class AssignResearchReviewerAction
{
    public function execute(int $itemId, string $reviewerEmail, int $assignedBy, ?string $dueOn = null): LibraryReviewAssignment
    {
        $item = LibraryItem::query()->findOrFail($itemId);
        if ($item->content_type !== LibraryContentType::Research) {
            throw ValidationException::withMessages(['item' => 'Only research items take peer reviewers.']);
        }
        $status = $item->status instanceof LibraryItemStatus ? $item->status : LibraryItemStatus::tryFrom((string) $item->status);
        if ($status !== LibraryItemStatus::Submitted) {
            throw ValidationException::withMessages(['item' => 'Only submitted items can be assigned a reviewer.']);
        }

        // R3b: the report is due in 14 days unless the office picks a day.
        $due = now()->addDays(LibraryReviewAssignment::DEFAULT_DUE_DAYS)->endOfDay();
        if ($dueOn !== null && trim($dueOn) !== '') {
            try {
                $due = Carbon::parse($dueOn, 'Indian/Maldives')->endOfDay();
            } catch (\Throwable) {
                throw ValidationException::withMessages(['due_on' => 'That is not a date.']);
            }
            if ($due->isPast()) {
                throw ValidationException::withMessages(['due_on' => 'The due date must be in the future.']);
            }
        }

        $userModel = config('auth.providers.users.model');
        $reviewer = $userModel::query()->where('email', trim($reviewerEmail))->first();
        if ($reviewer === null) {
            throw ValidationException::withMessages(['reviewer_email' => 'No user with that email.']);
        }

        // R3: one assignment per reviewer per item, in the item's current
        // round. Assigning again someone who reported in an earlier round
        // asks them to read the revised text — their old accept no longer
        // counts.
        $round = max(1, (int) $item->review_round);
        $assignment = LibraryReviewAssignment::query()->firstOrNew(
            ['library_item_id' => $item->id, 'reviewer_user_id' => $reviewer->id],
        );
        $fresh = ! $assignment->exists || (int) $assignment->round < $round;
        if ($fresh) {
            $assignment->fill(['assigned_by' => $assignedBy, 'status' => 'assigned', 'recommendation' => null, 'round' => $round, 'due_at' => $due, 'reminded_at' => null])->save();
        }
        $reviewer->assignRole('reviewer');

        if ($fresh) {
            app(NotifyLibraryUserAction::class)->execute(
                (int) $reviewer->id,
                'Research to review',
                'You have been asked to peer-review "'.$item->title.'" (round '.$round.'), due '.$due->timezone('Indian/Maldives')->toDateString().'.',
                '/review',
                'review_assigned',
            );
        }

        return $assignment->refresh();
    }
}
