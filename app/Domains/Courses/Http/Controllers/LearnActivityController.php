<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\AuthorizeActivityAccessAction;
use App\Domains\Courses\Actions\ResolveActivityDefinitionAction;
use App\Domains\Courses\Enums\ActivitySubmissionKind;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use App\Domains\Progress\Actions\AttachAttemptMediaAction;
use App\Domains\Progress\Actions\GetLatestActivityAttemptAction;
use App\Domains\Progress\Actions\SaveActivityAttemptAction;
use App\Domains\Progress\Actions\SubmitActivityAttemptAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearnActivityController extends Controller
{
    public function show(Request $request, int $activity): Response
    {
        abort_unless($request->user() !== null, 403);
        $access = app(AuthorizeActivityAccessAction::class)->execute($activity, (int) $request->user()->id);
        $attempt = app(GetLatestActivityAttemptAction::class)->execute($activity, $access['enrollment_id']);
        $settings = app(ResolveActivityDefinitionAction::class)->execute($activity, includeAnswerKeys: true)['settings'] ?? [];
        $showKeys = is_array($attempt)
            && ($attempt['status'] ?? null) === 'scored'
            && (bool) ($settings['show_correct_answer'] ?? false);

        return Inertia::render('Courses/Learn/Activity', [
            'activity' => app(ResolveActivityDefinitionAction::class)->execute($activity, includeAnswerKeys: $showKeys),
            'enrollment' => [
                'id' => $access['enrollment_id'],
                'course_id' => $access['course_id'],
            ],
            'attempt' => $attempt,
        ]);
    }

    public function autosave(Request $request, int $activity): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $access = app(AuthorizeActivityAccessAction::class)->execute($activity, (int) $request->user()->id);
        $definition = app(ResolveActivityDefinitionAction::class)->execute($activity, includeAnswerKeys: true);

        app(SaveActivityAttemptAction::class)->execute(
            $access['activity_id'],
            $access['enrollment_id'],
            $access['student_id'],
            $access['course_id'],
            $this->answers($request),
            is_array($definition['settings'] ?? null) ? $definition['settings'] : [],
            $access['academic_year_id'],
        );

        return back();
    }

    public function submit(Request $request, int $activity): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $access = app(AuthorizeActivityAccessAction::class)->execute($activity, (int) $request->user()->id);

        app(SubmitActivityAttemptAction::class)->execute(
            $access['activity_id'],
            $access['enrollment_id'],
            $access['student_id'],
            $access['course_id'],
            $this->answers($request),
            $access['academic_year_id'],
        );

        return back()->with('success', 'Activity submitted.');
    }

    /**
     * SPEC §36 "Play audio/voice submissions · View uploaded files".
     *
     * A teacher-marked activity could already declare `submission_kind: file`,
     * and the value was stored and never read: the player showed a text box
     * whatever it said, and no route accepted a file against an attempt. This
     * is the missing upload.
     */
    public function upload(Request $request, int $activity): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $access = app(AuthorizeActivityAccessAction::class)->execute($activity, (int) $request->user()->id);
        $kind = $this->submissionKind($activity);

        abort_unless($kind->acceptsUploads(), 403);
        $request->validate([
            'file' => ['required', 'file', 'max:'.(int) ceil(($kind->maxBytes() ?? 0) / 1024)],
        ]);

        $media = app(StorePrivateMediaAction::class)->execute(
            $request->file('file'),
            (int) $request->user()->id,
            $kind->allowedMimes(),
            $kind->maxBytes(),
        );

        app(AttachAttemptMediaAction::class)->attach(
            $access['activity_id'],
            $access['enrollment_id'],
            $access['student_id'],
            $access['course_id'],
            $media,
            $access['academic_year_id'],
        );

        return back()->with('success', 'File uploaded.');
    }

    public function removeAttachment(Request $request, int $activity, int $media): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $access = app(AuthorizeActivityAccessAction::class)->execute($activity, (int) $request->user()->id);

        app(AttachAttemptMediaAction::class)->detach($access['activity_id'], $access['enrollment_id'], $media);

        return back()->with('success', 'File removed.');
    }

    private function submissionKind(int $activity): ActivitySubmissionKind
    {
        $definition = app(ResolveActivityDefinitionAction::class)->execute($activity);

        return ActivitySubmissionKind::fromValue($definition['data']['submission_kind'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function answers(Request $request): array
    {
        $answers = $request->input('answers');
        if (is_string($answers)) {
            $answers = json_decode($answers, true) ?: [];
        }

        return is_array($answers) ? $answers : [];
    }
}
