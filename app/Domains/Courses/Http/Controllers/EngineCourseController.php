<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListCourseSubjectsAction;
use App\Domains\Courses\Actions\ListEngineCoursesAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Course;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EngineCourseController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render('Courses/Catalog/Index', [
            'rows' => app(ListEngineCoursesAction::class)->execute()->values(),
            'subjects' => app(ListCourseSubjectsAction::class)->execute()->values(),
            'statuses' => array_map(fn (CourseWorkflowStatus $status) => $status->value, CourseWorkflowStatus::cases()),
            'canPublish' => (bool) $request->user()?->can('courses.publish'),
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
        ]);
    }
}
