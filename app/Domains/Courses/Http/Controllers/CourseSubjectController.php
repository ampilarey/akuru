<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListCourseSubjectsAction;
use App\Domains\Courses\Actions\SaveCourseSubjectAction;
use App\Domains\Courses\Models\CourseSubject;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseSubjectController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render('Courses/Taxonomy/Subjects', [
            'rows' => app(ListCourseSubjectsAction::class)->execute()->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveCourseSubjectAction::class)->execute($this->validated($request));

        return redirect()->route('catalog.subjects.index')->with('success', 'Subject saved.');
    }

    public function update(Request $request, CourseSubject $subject): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveCourseSubjectAction::class)->execute($this->validated($request), $subject);

        return redirect()->route('catalog.subjects.index')->with('success', 'Subject updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $rows = app(ListCourseSubjectsAction::class)->execute();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'parent_id', 'name_en', 'slug', 'active']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['id'], $row['parent_id'], $row['name_en'], $row['slug'], $row['active'] ? '1' : '0']);
            }
            fclose($out);
        }, 'course-subjects.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:course_subjects,id'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_dv' => ['nullable', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);
    }
}
