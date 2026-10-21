<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Actions\SaveWriterPublicProfileAction;
use App\Domains\Library\Enums\BlogRequestStatus;
use App\Domains\Library\Models\BlogRequest;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\People\Actions\HasActiveConsentAction;
use App\Support\Said;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL1 (§4.1, D3): the office decides a student's blog request.
 *
 * Approval needs a request that has passed its parent: under 18 it needs
 * the parent's `blog_publishing` consent still in force (a parent who
 * withdrew it on the student's record stops the approval). It makes the
 * student's writer profile — `kind = student`, the pen name as the name the
 * public sees, the parent's two choices — and grants `writer`, as a writer
 * application's approval does (ROADMAP §9: a role on the one identity).
 *
 * A refusal says why: the note is required, and the student reads it.
 */
class DecideBlogRequestAction
{
    public function execute(int $requestId, int $decidedBy, bool $approve, ?string $note = null): BlogRequest
    {
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        $request = DB::transaction(function () use ($requestId, $decidedBy, $approve, $note) {
            $request = BlogRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();
            if (! $request->status->isOpen()) {
                throw ValidationException::withMessages(['decision' => __('blog.error_request_decided')]);
            }

            if (! $approve) {
                if ($note === null) {
                    throw ValidationException::withMessages(['note' => __('blog.error_note_required')]);
                }
                $request->fill(['status' => BlogRequestStatus::Refused, 'decided_by' => $decidedBy, 'decided_at' => now(), 'decision_note' => $note])->save();

                return $request;
            }

            if ($request->status !== BlogRequestStatus::WaitingOffice) {
                throw ValidationException::withMessages(['decision' => __('blog.error_waiting_parent')]);
            }
            if ($request->under_18 && ($request->guardian_user_id === null
                || ! app(HasActiveConsentAction::class)->execute('student', (int) $request->student_id, 'blog_publishing'))) {
                throw ValidationException::withMessages(['decision' => __('blog.error_no_consent')]);
            }
            if (WriterProfile::query()->where('user_id', $request->user_id)->exists()) {
                throw ValidationException::withMessages(['decision' => __('blog.error_already_writer')]);
            }

            WriterProfile::query()->create([
                'user_id' => $request->user_id,
                'kind' => 'student',
                'display_name' => $request->pen_name,
                'blog_title' => $request->blog_title,
                'slug' => SaveWriterPublicProfileAction::slugFor($request->pen_name),
                'bio' => $request->about,
                'show_photo' => (bool) $request->show_photo,
                'show_school' => (bool) $request->show_school,
                'guardian_user_id' => $request->under_18 ? $request->guardian_user_id : null,
                'guardian_consented_at' => $request->under_18 ? $request->guardian_decided_at : null,
                'status' => 'active',
                'approved_at' => now(),
                'approved_by' => $decidedBy,
            ]);
            $userModel = config('auth.providers.users.model');
            $userModel::query()->findOrFail($request->user_id)->assignRole('writer');

            $request->fill(['status' => BlogRequestStatus::Approved, 'decided_by' => $decidedBy, 'decided_at' => now(), 'decision_note' => $note])->save();

            return $request;
        });

        app(NotifyLibraryUserAction::class)->execute(
            (int) $request->user_id,
            Said::of('library_notices.'.($approve ? 'blog_ready_title' : 'blog_refused_title')),
            $approve ? Said::of('library_notices.blog_ready_body', ['title' => $request->blog_title]) : (string) $request->decision_note,
            '/my-blog',
            'blog_request_decided',
        );

        return $request;
    }
}
