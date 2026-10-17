<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Enums\BlogRequestStatus;
use App\Domains\Library\Models\BlogRequest;
use App\Domains\People\Actions\ListConsentingGuardiansAction;
use App\Domains\People\Actions\RecordConsentAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL1 (§4.1, D7): a verified parent agrees to their child's blog,
 * or says no. The answer is a People consent (`blog_publishing`, rule 11:
 * one consent record), and with a yes the parent also chooses whether the
 * child's photo and school name may show. One parent's yes is enough.
 *
 * The photo choice is this blog's own, not the school's photo consent
 * (`photo_media_use`), which covers the school's media and is not widened
 * by a blog page.
 */
class ConsentToBlogRequestAction
{
    public function execute(int $requestId, int $guardianUserId, bool $agree, bool $showPhoto = false, bool $showSchool = false): BlogRequest
    {
        $request = DB::transaction(function () use ($requestId, $guardianUserId, $agree, $showPhoto, $showSchool) {
            $request = BlogRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();
            if (! in_array($guardianUserId, app(ListConsentingGuardiansAction::class)->execute((int) $request->student_id), true)) {
                throw ValidationException::withMessages(['consent' => __('blog.error_not_your_child')]);
            }
            if ($request->status !== BlogRequestStatus::WaitingParent) {
                throw ValidationException::withMessages(['consent' => __('blog.error_not_waiting_parent')]);
            }

            app(RecordConsentAction::class)->execute('student', (int) $request->student_id, 'blog_publishing', $agree, $guardianUserId, 'portal');

            $request->fill([
                'status' => $agree ? BlogRequestStatus::WaitingOffice : BlogRequestStatus::ParentDeclined,
                'guardian_user_id' => $guardianUserId,
                'guardian_decided_at' => now(),
                'show_photo' => $agree && $showPhoto,
                'show_school' => $agree && $showSchool,
            ])->save();

            return $request;
        });

        $notify = app(NotifyLibraryUserAction::class);
        $notify->execute(
            (int) $request->user_id,
            $agree ? 'Your parent agreed to your blog' : 'Your parent said not now',
            $agree ? 'The office will look at your blog request next.' : 'Your blog request will not go ahead. You may ask again later.',
            '/my-blog',
        );
        if ($agree) {
            $notify->office('New blog request', "{$request->pen_name} asked to start a blog, and a parent agreed.", '/admin/library/blog-requests');
        }

        return $request;
    }
}
