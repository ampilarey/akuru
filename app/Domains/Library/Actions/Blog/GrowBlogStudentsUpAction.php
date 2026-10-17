<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\People\Actions\ResolveStudentAgeForUserAction;

/**
 * BLOG_PLAN BL7 (D15): a student blogger who has turned 18 becomes a full
 * writer — the same profile, so the page, posts, series, followers and
 * earnings stay. The parent's part ends (the consenting parent and their
 * choices are cleared), and both are told. Run daily.
 *
 * @return int how many blogs moved
 */
class GrowBlogStudentsUpAction
{
    public function execute(): int
    {
        $ages = app(ResolveStudentAgeForUserAction::class);
        $notify = app(NotifyLibraryUserAction::class);
        $moved = 0;
        foreach (WriterProfile::query()->where('kind', 'student')->where('status', 'active')->get() as $profile) {
            $age = $ages->execute((int) $profile->user_id)['age'] ?? null;
            if ($age === null || $age < 18) {
                continue;
            }
            $parent = $profile->guardian_user_id;
            $profile->forceFill(['kind' => 'writer', 'guardian_user_id' => null, 'guardian_consented_at' => null, 'show_photo' => true])->save();
            $moved++;
            $notify->execute((int) $profile->user_id, 'Your blog is a writer\'s blog now', 'You are 18: your blog, posts, followers and earnings stay yours, and the writer portal is open to you.', '/write');
            if ($parent !== null) {
                $notify->execute((int) $parent, 'Your child\'s blog is theirs now', $profile->display_name.'\'s blog moved to their own care at 18. Your consent for it has ended.', '/portal/children');
            }
        }

        return $moved;
    }
}
