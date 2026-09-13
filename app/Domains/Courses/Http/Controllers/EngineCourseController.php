<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListCourseSubjectsAction;
use App\Domains\Courses\Actions\ListEngineCoursesAction;
use App\Domains\Courses\Actions\RecordCourseReviewDecisionAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseReviewDecision;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Enums\UnlockMode;
use App\Domains\Courses\Models\Course;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EngineCourseController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        $rows = app(ListEngineCoursesAction::class)->execute()->values();

        // SPEC §34 "View supervisor comments" and §35 "View courses submitted
        // for review". Both belong on the screen the creator and the supervisor
        // already work from — the transition buttons are here — rather than
        // behind another nav link.
        $decisions = app(RecordCourseReviewDecisionAction::class)
            ->forCourses($rows->pluck('id')->map(fn ($id): int => (int) $id)->all());

        return Inertia::render('Courses/Catalog/Index', [
            'rows' => $rows->map(fn (array $row): array => $row + [
                'review_decisions' => $decisions[$row['id']] ?? [],
            ])->values(),
            'decisions' => array_map(
                fn (CourseReviewDecision $decision): array => [
                    'value' => $decision->value,
                    'label' => $decision->label(),
                    'requires_comment' => $decision->requiresComment(),
                ],
                CourseReviewDecision::cases(),
            ),
            'subjects' => app(ListCourseSubjectsAction::class)->execute()->values(),
            'statuses' => array_map(fn (CourseWorkflowStatus $status) => $status->value, CourseWorkflowStatus::cases()),
            'canPublish' => (bool) $request->user()?->can('courses.publish'),
            'unlockModes' => array_map(
                fn (UnlockMode $mode) => ['value' => $mode->value, 'label' => $mode->label()],
                UnlockMode::courseLevelCases(),
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveEngineCourseAction::class)->execute($this->validated($request) + [
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('catalog.courses.index')->with('success', 'Course saved as draft.');
    }

    public function update(Request $request, int $course): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveEngineCourseAction::class)->execute(
            $this->validated($request),
            Course::query()->findOrFail($course),
        );

        return redirect()->route('catalog.courses.index')->with('success', 'Course updated.');
    }

    /**
     * SPEC §35 "Approve courses · Reject courses · Request changes", and the
     * other half of §34's "View supervisor comments".
     *
     * The workflow already moved a course; what it never did was record **why**.
     * "Return draft" bounced a course back with no comment, no reviewer and no
     * date, so a creator was told nothing and the supervisor's actual review
     * was discarded the moment the button was pressed.
     */
    public function decide(Request $request, int $course): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::enum(CourseReviewDecision::class)],
            'comment' => ['nullable', 'string', 'max:5000'],
            'academic_year_id' => ['nullable', 'integer'],
        ]);

        app(RecordCourseReviewDecisionAction::class)->execute(
            Course::query()->findOrFail($course),
            CourseReviewDecision::from($data['decision']),
            $data,
            (int) $request->user()->id,
            (bool) $request->user()?->can('courses.publish'),
        );

        return redirect()->route('catalog.courses.index')->with('success', 'Review recorded.');
    }

    public function transition(Request $request, int $course): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $data = $request->validate([
            'workflow_status' => ['required', 'string'],
        ]);
        $to = CourseWorkflowStatus::tryFrom($data['workflow_status']);
        abort_unless($to instanceof CourseWorkflowStatus, 422);

        app(TransitionCourseWorkflowAction::class)->execute(
            Course::query()->findOrFail($course),
            $to,
            (bool) $request->user()?->can('courses.publish'),
        );

        return redirect()->route('catalog.courses.index')->with('success', 'Course status updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $rows = app(ListEngineCoursesAction::class)->execute();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'title', 'slug', 'subject_name', 'workflow_status']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['id'], $row['title'], $row['slug'], $row['subject_name'], $row['workflow_status']]);
            }
            fclose($out);
        }, 'engine-courses.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'title_dv' => ['nullable', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer', 'exists:course_subjects,id'],
            'short_desc' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'language' => ['nullable', 'string', 'max:16'],
            // SPEC §26 "Admin must be able to configure unlock rules."
            'unlock_mode' => ['nullable', Rule::enum(UnlockMode::class)],
        ]);
    }
}
