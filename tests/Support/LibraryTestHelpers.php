<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryReviewAssignment;

/**
 * R3 (RESEARCH_ARTICLES_PLAN D2): research is published only once peer
 * review has accepted it. A fixture that needs a published research item
 * records the accept first — in the item's current round — the way a
 * reviewer's report would.
 */
function peerAccept(LibraryItem|int $item, ?User $reviewer = null): LibraryReviewAssignment
{
    $item = $item instanceof LibraryItem ? $item : LibraryItem::query()->findOrFail($item);
    $reviewer ??= User::factory()->create();

    return LibraryReviewAssignment::query()->create([
        'library_item_id' => $item->id,
        'reviewer_user_id' => $reviewer->id,
        'status' => 'done',
        'recommendation' => 'accept',
        'round' => max(1, (int) $item->review_round),
    ]);
}
