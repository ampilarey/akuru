<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryReviewAssignment;
use Illuminate\Validation\ValidationException;

/**
 * L7 (§12.2): the editor hands a SUBMITTED research item to a peer
 * reviewer (any user, picked by email — reviewer is a role on the
 * unified identity). One assignment per reviewer per item.
 */
class AssignResearchReviewerAction
{
    public function execute(int $itemId, string $reviewerEmail, int $assignedBy): LibraryReviewAssignment
    {
        $item = LibraryItem::query()->findOrFail($itemId);
        if ($item->content_type !== LibraryContentType::Research) {
            throw ValidationException::withMessages(['item' => 'Only research items take peer reviewers.']);
        }
        $status = $item->status instanceof LibraryItemStatus ? $item->status : LibraryItemStatus::tryFrom((string) $item->status);
        if ($status !== LibraryItemStatus::Submitted) {
            throw ValidationException::withMessages(['item' => 'Only submitted items can be assigned a reviewer.']);
        }

        $userModel = config('auth.providers.users.model');
        $reviewer = $userModel::query()->where('email', trim($reviewerEmail))->first();
        if ($reviewer === null) {
            throw ValidationException::withMessages(['reviewer_email' => 'No user with that email.']);
        }

        $assignment = LibraryReviewAssignment::query()->firstOrCreate(
            ['library_item_id' => $item->id, 'reviewer_user_id' => $reviewer->id],
            ['assigned_by' => $assignedBy, 'status' => 'assigned'],
        );
        $reviewer->assignRole('reviewer');

        return $assignment;
    }
}
