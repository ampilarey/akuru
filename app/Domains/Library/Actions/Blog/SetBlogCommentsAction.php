<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Models\WriterProfile;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL5: the writer's comments switch. It is the writer's own
 * choice, not a page change, so it takes effect at once; an under-13's blog
 * takes no comments whatever it says (D13).
 */
class SetBlogCommentsAction
{
    public function execute(int $userId, bool $enabled): WriterProfile
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->where('status', 'active')->first();
        if ($profile === null) {
            throw ValidationException::withMessages(['comments' => __('blog.error_no_blog')]);
        }
        $profile->fill(['comments_enabled' => $enabled])->save();

        return $profile;
    }
}
