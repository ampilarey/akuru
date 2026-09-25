<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemReview;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Validation\ValidationException;

/**
 * L5: a writer hands their own draft (or change-requested revision) to the
 * editorial queue. Logged in the append-only review trail.
 */
class SubmitLibraryItemForReviewAction
{
    public function execute(int $userId, int $itemId): LibraryItem
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
        $item->save();

        LibraryItemReview::query()->create([
            'library_item_id' => $item->id,
            'reviewer_user_id' => null,
            'decision' => 'submitted',
            'comment' => null,
        ]);

        // §41: the writer hears it arrived; the office hears there is work.
        $notify = app(NotifyLibraryUserAction::class);
        $notify->execute($userId, 'Submission received', '"'.$item->title.'" is in the editorial queue. You will hear when it is reviewed.', '/write');
        $notify->office('New library submission', $profile->display_name.' submitted "'.$item->title.'" for review.', '/admin/library');

        return $item->refresh();
    }
}
