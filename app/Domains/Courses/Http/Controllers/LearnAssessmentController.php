<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\AuthorizeAssessmentAccessAction;
use App\Domains\Courses\Actions\ResolveAssessmentSettingsAction;
use App\Domains\Progress\Actions\GetLatestAssessmentAttemptAction;
use App\Domains\Progress\Actions\ResolveRetakeStateAction;
use App\Domains\Progress\Actions\SaveAssessmentAttemptAction;
use App\Domains\Progress\Actions\StartAssessmentAttemptAction;
use App\Domains\Progress\Actions\SubmitAssessmentAttemptAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearnAssessmentController extends Controller
{
    public function show(Request $request, int $assessment): Response
    {
        abort_unless($request->user() !== null, 403);
        $access = app(AuthorizeAssessmentAccessAction::class)->execute($assessment, (int) $request->user()->id);
        $settings = app(ResolveAssessmentSettingsAction::class)->execute($assessment);
        $attempt = $this->attemptToShow($assessment, $access, $settings);

        return Inertia::render('Courses/Learn/Assessment', [
            'assessment' => $settings,
            'enrollment' => [
                'id' => $access['enrollment_id'],
                'course_id' => $access['course_id'],
                'classroom_id' => $access['classroom_id'],
            ],
            'attempt' => $attempt,
            // The retake policy the author set, told to the person it is about.
            // `retake_limit` has always been configurable and enforced, and the
            // player never learned of it — so once an attempt existed this page
            // was frozen for ever and nobody could use a second go.
            'retake' => app(ResolveRetakeStateAction::class)->forAssessment(
                $access['assessment_id'],
                $access['enrollment_id'],
                $access['student_id'],
                $settings['retake_limit'] ?? null,
            ),
            // SPEC §20's question attachments are private media. The same id is
            // served to an author at `/catalog/media` and to a student here, so
            // the prefix is a prop exactly as it is in the lesson player rather
            // than a URL frozen into the snapshot.
            'mediaShowUrl' => '/learn/media',
        ]);
    }

    /**
     * Which attempt this student should be looking at.
     *
     * Lifted out of `show` when the retake prop pushed it past its baselined
     * length — the gate's point being that a controller method accumulating
     * branches is exactly what should move, rather than the number being
     * raised to fit. Three decisions live here: the latest attempt, starting
     * the first one if there is none, and re-reading it with answer keys when
     * §19's `show_correct_answers` allows.
     *
     * @param  array<string, mixed>  $access
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function attemptToShow(int $assessment, array $access, array $settings): array
    {
        $attempt = app(GetLatestAssessmentAttemptAction::class)->execute(
            $assessment,
            $access['enrollment_id'],
            studentId: $access['student_id'],
            // SPEC §19 `show_results`: this is the student looking at their own
            // attempt, which is the only place the setting is about.
            asStudent: true,
        );

        if ($attempt === null) {
            return app(StartAssessmentAttemptAction::class)->execute(
                $access['assessment_id'],
                $access['enrollment_id'],
                $access['student_id'],
                $access['course_id'],
                $access['academic_year_id'],
                $access['classroom_id'],
            );
        }

        if (($attempt['status'] ?? null) !== 'scored' || ! (bool) $settings['show_correct_answers']) {
            return $attempt;
        }

        return app(GetLatestAssessmentAttemptAction::class)->execute(
            $assessment,
            $access['enrollment_id'],
            includeKeys: true,
            studentId: $access['student_id'],
            asStudent: true,
        );
    }

    /**
     * Start the next attempt.
     *
     * Unlike an activity — where submitting again is enough, because
     * `SubmitActivityAttemptAction` creates the attempt it needs — an
     * assessment attempt carries **question snapshots**, frozen at the moment
     * it starts and randomised per attempt when the author asked for that. So
     * a retake has to be begun on the server, not merely answered.
     *
     * Thin (rule 5): `StartAssessmentAttemptAction` already refuses when no
     * retake remains, using the same reader that decided whether to show the
     * button.
     */
    public function retake(Request $request, int $assessment): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $access = app(AuthorizeAssessmentAccessAction::class)->execute($assessment, (int) $request->user()->id);

        app(StartAssessmentAttemptAction::class)->execute(
            $access['assessment_id'],
            $access['enrollment_id'],
            $access['student_id'],
            $access['course_id'],
            $access['academic_year_id'],
            $access['classroom_id'],
        );

        return back()->with('success', 'Started again.');
    }

    public function autosave(Request $request, int $assessment): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $access = app(AuthorizeAssessmentAccessAction::class)->execute($assessment, (int) $request->user()->id);
        app(SaveAssessmentAttemptAction::class)->execute(
            $access['assessment_id'],
            $access['enrollment_id'],
            $this->answers($request),
            $access['student_id'],
        );

        return back();
    }

    public function submit(Request $request, int $assessment): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $access = app(AuthorizeAssessmentAccessAction::class)->execute($assessment, (int) $request->user()->id);
        app(SubmitAssessmentAttemptAction::class)->execute(
            $access['assessment_id'],
            $access['enrollment_id'],
            $this->answers($request),
            $access['student_id'],
        );

        return back()->with('success', 'Assessment submitted.');
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
