<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Models\LibraryTip;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL6a (§3.6): the office decides a tip's thank-you note, as it
 * does a comment — published, the writer reads it on *My blog*; refused, it
 * never reaches them. The tip's money is untouched.
 */
class ModerateTipNoteAction
{
    public function execute(int $tipId, int $officeUserId, bool $publish): LibraryTip
    {
        $tip = LibraryTip::query()->whereKey($tipId)->whereNotNull('note')->first();
        if ($tip === null) {
            throw ValidationException::withMessages(['note' => __('blog.error_no_tip_note')]);
        }
        $tip->fill(['note_status' => $publish ? 'published' : 'refused', 'note_decided_by' => $officeUserId, 'note_decided_at' => now()])->save();

        return $tip;
    }
}
