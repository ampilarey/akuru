<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Support\Said;
use Illuminate\Validation\ValidationException;

/**
 * Business rule §43.3: admin must approve content — publishing stamps
 * approved_by. Unpublish returns to draft without clearing history.
 * Research is refused until peer review has accepted it (R3).
 */
class PublishLibraryItemAction
{
    public function execute(int $itemId, int $approvedBy, bool $publish = true): LibraryItem
    {
        $item = LibraryItem::query()->findOrFail($itemId);
        if ($publish) {
            // R3 (D2): research goes out only once peer review says so —
            // whichever screen asked.
            app(AssertResearchReviewedAction::class)->execute($item);
            // BLOG_PLAN BL2a: nor a student's post its class teacher has not checked.
            if ($item->review_stage === 'teacher') {
                throw ValidationException::withMessages(['item' => __('blog.error_with_teacher')]);
            }
            $item->fill([
                'status' => LibraryItemStatus::Published,
                'published_at' => $item->published_at ?? now(),
                'approved_by' => $approvedBy,
            ]);
        } else {
            $item->status = LibraryItemStatus::Draft;
        }
        $item->save();

        // §41: a writer hears their work went live, with where it is.
        if ($publish && $item->writer?->user_id) {
            app(NotifyLibraryUserAction::class)->execute(
                (int) $item->writer->user_id,
                Said::of('library_notices.published_title'), Said::of('library_notices.on_shelf_body', ['title' => $item->title]),
                '/library/'.$item->slug,
                'published',
            );
            // B11: and so do the readers who already read that writer.
            app(NotifyReadersOfNewWorkAction::class)->execute($item->refresh());
        }

        return $item->refresh();
    }
}
