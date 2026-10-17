<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\Library\Support\BlogPage;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL3a (D14): the office decides a blog page's waiting design.
 * This is the one place the live design changes: approval writes the whole
 * set to the live columns (the *about* text is the profile's `bio`); a
 * refusal needs a note, and leaves the set with the writer to fix.
 */
class DecideBlogPageAction
{
    public function execute(int $profileId, int $decidedBy, bool $approve, ?string $note = null): WriterProfile
    {
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;
        $profile = WriterProfile::query()->findOrFail($profileId);
        if ($profile->page_submitted_at === null || $profile->pending_page === null) {
            throw ValidationException::withMessages(['page' => __('blog.error_no_page_change')]);
        }

        if ($approve) {
            // Checked again: a theme may have been retired since it was chosen.
            $design = BlogPage::normalize((array) $profile->pending_page);
            if (BlogPage::problems($design) !== []) {
                throw ValidationException::withMessages(['page' => __('blog.error_contrast_short')]);
            }
            $profile->fill([
                'theme_key' => $design['theme'], 'accent_color' => $design['accent'], 'heading_font' => $design['font'],
                'tagline' => $design['tagline'], 'bio' => $design['about'], 'languages' => $design['languages'], 'sections' => $design['sections'],
                'pending_page' => null, 'page_submitted_at' => null, 'page_decision_note' => null,
            ])->save();
        } else {
            if ($note === null) {
                throw ValidationException::withMessages(['note' => __('blog.error_note_required')]);
            }
            $profile->fill(['page_submitted_at' => null, 'page_decision_note' => $note])->save();
        }

        app(NotifyLibraryUserAction::class)->execute(
            (int) $profile->user_id,
            $approve ? 'Your blog page is updated' : 'Your blog page changes were not approved',
            $approve ? 'Readers now see your new page.' : $note,
            '/my-blog/design',
            'post_decided',
        );

        return $profile->refresh();
    }
}
