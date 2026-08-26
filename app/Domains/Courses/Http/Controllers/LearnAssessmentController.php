<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\AuthorizeAssessmentAccessAction;
use App\Domains\Courses\Actions\ResolveAssessmentSettingsAction;
use App\Domains\Progress\Actions\GetLatestAssessmentAttemptAction;
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
        $attempt = app(GetLatestAssessmentAttemptAction::class)->execute(
            $assessment,
            $access['enrollment_id'],
            studentId: $access['student_id'],
        );
        if ($attempt === null) {
            $attempt = app(StartAssessmentAttemptAction::class)->execute(
                $access['assessment_id'],
                $access['enrollment_id'],
                $access['student_id'],
                $access['course_id'],
                $access['academic_year_id'],
                $access['classroom_id'],
            );
        }

        $showKeys = ($attempt['status'] ?? null) === 'scored' && (bool) $settings['show_correct_answers'];
        if ($showKeys) {
            $attempt = app(GetLatestAssessmentAttemptAction::class)->execute(
                $assessment,
                $access['enrollment_id'],
                includeKeys: true,
                studentId: $access['student_id'],
            );
        }

        return Inertia::render('Courses/Learn/Assessment', [
            'assessment' => $settings,
            'enrollment' => [
                'id' => $access['enrollment_id'],
                'course_id' => $access['course_id'],
                'classroom_id' => $access['classroom_id'],
            ],
            'attempt' => $attempt,
        ]);
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
