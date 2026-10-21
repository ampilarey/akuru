<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Enums\BlogRequestStatus;
use App\Domains\Library\Models\BlogRequest;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\People\Actions\ListConsentingGuardiansAction;
use App\Domains\People\Actions\ResolveStudentAgeForUserAction;
use App\Support\Said;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL1 (§4.1, STATUS §5se): a student asks to start a blog. Under
 * 18 — or of an age the record cannot tell — the request waits for a
 * parent, and each verified parent is told; at 18 it goes to the office.
 * One open request at a time; none for someone who already writes.
 *
 * A pen name keeps a child findable only by those who know them (D9): one
 * that carries the student's surname is refused.
 */
class StartStudentBlogAction
{
    /**
     * @param  array{pen_name: string, blog_title: string, about: string}  $data
     */
    public function execute(int $userId, array $data): BlogRequest
    {
        $student = app(ResolveStudentAgeForUserAction::class)->execute($userId);
        if ($student === null) {
            throw ValidationException::withMessages(['pen_name' => __('blog.error_not_student')]);
        }
        if (WriterProfile::query()->where('user_id', $userId)->exists()) {
            throw ValidationException::withMessages(['pen_name' => __('blog.error_already_writer')]);
        }

        $penName = trim($data['pen_name']);
        $surname = mb_strtolower(trim($student['last_name']));
        if (mb_strlen($surname) >= 3 && str_contains(mb_strtolower($penName), $surname)) {
            throw ValidationException::withMessages(['pen_name' => __('blog.error_pen_name_surname')]);
        }

        $under18 = $student['age'] === null || $student['age'] < 18;

        $request = DB::transaction(function () use ($userId, $student, $data, $penName, $under18) {
            $open = BlogRequest::query()->where('user_id', $userId)
                ->whereIn('status', [BlogRequestStatus::WaitingParent->value, BlogRequestStatus::WaitingOffice->value])
                ->lockForUpdate()->exists();
            if ($open) {
                throw ValidationException::withMessages(['pen_name' => __('blog.error_request_open')]);
            }

            return BlogRequest::query()->create([
                'user_id' => $userId,
                'student_id' => $student['student_id'],
                'pen_name' => $penName,
                'blog_title' => trim($data['blog_title']),
                'about' => trim($data['about']),
                'under_18' => $under18,
                'status' => $under18 ? BlogRequestStatus::WaitingParent : BlogRequestStatus::WaitingOffice,
                'show_photo' => ! $under18,
            ]);
        });

        $notify = app(NotifyLibraryUserAction::class);
        if ($under18) {
            foreach (app(ListConsentingGuardiansAction::class)->execute($student['student_id']) as $guardianUserId) {
                $notify->execute(
                    $guardianUserId,
                    Said::of('library_notices.blog_consent_title'),
                    Said::of('library_notices.blog_consent_body', ['name' => $student['first_name'], 'pen' => $penName]),
                    "/portal/children/{$student['student_id']}/blog",
                    'blog_consent_asked',
                );
            }
        } else {
            $notify->office(Said::of('library_notices.blog_request_title'), Said::of('library_notices.blog_request_body', ['name' => $penName]), '/admin/library/blog-requests');
        }

        return $request;
    }
}
