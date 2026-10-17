<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Models\WriterProfile;
use App\Domains\Library\Support\BlogPage;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL3a (§5, D14): a blogger saves their page's new design as a
 * draft. The live page does not change: the whole set waits in
 * `pending_page` until the office approves it. Colours that do not read are
 * refused with the reason; a set already sent cannot be changed until it is
 * decided.
 */
class SaveBlogPageDraftAction
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(int $userId, array $input): WriterProfile
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->where('status', 'active')->first();
        if ($profile === null) {
            throw ValidationException::withMessages(['page' => __('blog.error_no_blog')]);
        }
        if ($profile->page_submitted_at !== null) {
            throw ValidationException::withMessages(['page' => __('blog.error_page_waiting')]);
        }

        $design = BlogPage::normalize($input);
        $problems = BlogPage::problems($design);
        if ($problems !== []) {
            throw ValidationException::withMessages(['accent' => __('blog.error_contrast', [
                'ratio' => number_format($problems[0]['ratio'], 1),
                'needed' => number_format($problems[0]['needed'], 1),
            ])]);
        }

        $profile->fill(['pending_page' => $design, 'page_decision_note' => null])->save();

        return $profile->refresh();
    }
}
