<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Models\WriterProfile;
use App\Support\Said;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL3a (D14): the blogger sends the waiting design to the office.
 * One approval covers the whole set.
 */
class SubmitBlogPageAction
{
    public function execute(int $userId): WriterProfile
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->where('status', 'active')->first();
        if ($profile === null || $profile->pending_page === null) {
            throw ValidationException::withMessages(['page' => __('blog.error_no_page_change')]);
        }
        if ($profile->page_submitted_at !== null) {
            throw ValidationException::withMessages(['page' => __('blog.error_page_waiting')]);
        }
        $profile->fill(['page_submitted_at' => now()])->save();

        app(NotifyLibraryUserAction::class)->office(Said::of('library_notices.blog_page_title'), Said::of('library_notices.blog_page_body', ['name' => $profile->display_name]), '/admin/library/blog-pages');

        return $profile->refresh();
    }
}
