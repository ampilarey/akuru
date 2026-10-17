<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Models\WriterProfile;
use Illuminate\Validation\ValidationException;

/** BLOG_PLAN BL6a (§3.6): the writer turns the tip box on their blog on or off. */
class SetBlogTipsAction
{
    public function execute(int $userId, bool $enabled): WriterProfile
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->where('status', 'active')->first();
        if ($profile === null) {
            throw ValidationException::withMessages(['tips' => __('blog.error_no_blog')]);
        }
        $profile->fill(['tips_enabled' => $enabled])->save();

        return $profile;
    }
}
