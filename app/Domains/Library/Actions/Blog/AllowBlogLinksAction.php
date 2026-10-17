<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Enums\BlogRequestStatus;
use App\Domains\Library\Models\BlogRequest;
use App\Domains\People\Actions\HasActiveConsentAction;
use App\Domains\People\Actions\RecordConsentAction;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL3b (§5, §9 "no links without consent"): a parent lets their
 * child's approved blog carry links to other sites, or stops it. The answer
 * is a People consent, `blog_links`, on the student's record — so it is
 * asked fresh wherever links would show, and withdrawing it takes them off
 * at once. The caller has already checked this is the parent's own child.
 */
class AllowBlogLinksAction
{
    public function execute(int $studentId, int $guardianUserId, bool $allow): void
    {
        $approved = BlogRequest::query()->where('student_id', $studentId)->where('status', BlogRequestStatus::Approved->value)->exists();
        if (! $approved) {
            throw ValidationException::withMessages(['links' => __('blog.error_no_blog_yet')]);
        }
        app(RecordConsentAction::class)->execute('student', $studentId, 'blog_links', $allow, $guardianUserId, 'portal');
    }

    public function allowed(int $studentId): bool
    {
        return app(HasActiveConsentAction::class)->execute('student', $studentId, 'blog_links');
    }
}
