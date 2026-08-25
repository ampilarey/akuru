<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\AuthorizeActivityAccessAction;
use App\Domains\Courses\Actions\ResolveActivityDefinitionAction;
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
