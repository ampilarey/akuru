<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Academics\Actions\ResolveClassTeacherForStudentAction;
use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemReview;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\People\Actions\ResolveStudentAgeForUserAction;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL2a (§6): a blogger sends a post to be checked.
 *
 *  - A student's post goes to their class teacher first (`review_stage =
 *    teacher`), then the office; with no class teacher, or with the teacher
 *    step turned off (D6), straight to the office.
 *  - A full writer's post goes to the office.
 *  - A post needs its *own work* tick (the copyright declaration) and a
 *    text. No ID card: a student has none, and a post is not sold.
 *  - A student sends at most `library.blog.posts_per_week` posts in seven
 *    days, counting the ones waiting and published (D18).
 *
 * Logged in the item's review trail, as every submission is.
 *
 * BL2c: a published post's waiting edit goes the same way — the class
 * teacher for a student, then the office — while the post stays published
 * with its live text. An edit is not a new post, so the weekly cap does not
 * count it.
 */
class SubmitBlogPostAction
{
    public function execute(int $userId, int $itemId): LibraryItem
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->where('status', 'active')->first();
        $item = LibraryItem::query()->findOrFail($itemId);
        if ($profile === null || (int) $item->writer_id !== (int) $profile->id || $item->content_type !== LibraryContentType::Post) {
            throw ValidationException::withMessages(['post' => __('blog.error_post_own')]);
        }
        $change = $item->status === LibraryItemStatus::Published;
        if ($change && (! $item->hasPendingChange() || $item->review_stage !== null)) {
            throw ValidationException::withMessages(['post' => __('blog.error_no_change_to_send')]);
        }
        if (! $change && ! in_array($item->status, [LibraryItemStatus::Draft, LibraryItemStatus::ChangesRequested], true)) {
            throw ValidationException::withMessages(['post' => __('blog.error_post_not_submittable')]);
        }
        if (empty($item->declarations['copyright'])) {
            throw ValidationException::withMessages(['own_work' => __('blog.error_own_work')]);
        }
        if (trim(strip_tags((string) ($change ? $item->pending_body : $item->body))) === '') {
            throw ValidationException::withMessages(['body' => __('blog.error_post_empty')]);
        }

        $teacher = null;
        if ($profile->isStudent() && ! $change) {
            $limit = max(1, (int) config('library.blog.posts_per_week', 3));
            $recent = LibraryItem::query()
                ->where('writer_id', $profile->id)
                ->where('content_type', LibraryContentType::Post->value)
                ->whereKeyNot($item->id)
                ->whereIn('status', [LibraryItemStatus::Submitted->value, LibraryItemStatus::Published->value])
                ->where('submitted_at', '>=', now()->subDays(7))
                ->count();
            if ($recent >= $limit) {
                throw ValidationException::withMessages(['post' => trans_choice('blog.error_weekly_cap', $limit, ['count' => $limit])]);
            }
        }
        if ($profile->isStudent() && config('library.blog.teacher_review', true)) {
            $student = app(ResolveStudentAgeForUserAction::class)->execute($userId);
            if ($student !== null) {
                $teacher = app(ResolveClassTeacherForStudentAction::class)->execute($student['student_id']);
            }
        }

        $item->fill([
            'review_stage' => $teacher !== null ? 'teacher' : 'office',
            'teacher_user_id' => $teacher,
        ] + ($change ? [] : ['status' => LibraryItemStatus::Submitted]));
        if (! $change) {
            $item->submitted_at = now();
        }
        $item->save();
        LibraryItemReview::query()->create(['library_item_id' => $item->id, 'reviewer_user_id' => null, 'decision' => $change ? 'edit_submitted' : 'submitted', 'comment' => null]);

        $notify = app(NotifyLibraryUserAction::class);
        if ($teacher !== null) {
            $notify->execute($teacher, 'A student\'s post to check', $profile->display_name.' sent "'.$item->title.'" for you to check.', '/teach/blog-posts');
        } else {
            $notify->office('New blog post to check', $profile->display_name.' sent "'.$item->title.'" for checking.', '/admin/library');
        }

        return $item->refresh();
    }
}
