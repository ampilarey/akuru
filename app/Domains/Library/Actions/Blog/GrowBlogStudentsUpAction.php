<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\People\Actions\ResolveStudentAgeForUserAction;
use App\Support\Said;

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
            $notify->execute((int) $profile->user_id, Said::of('library_notices.grown_up_title'), Said::of('library_notices.grown_up_body'), '/write');
            if ($parent !== null) {
                $notify->execute((int) $parent, Said::of('library_notices.grown_up_parent_title'), Said::of('library_notices.grown_up_parent_body', ['name' => $profile->display_name]), '/portal/children');
            }
        }

        return $moved;
    }
}
