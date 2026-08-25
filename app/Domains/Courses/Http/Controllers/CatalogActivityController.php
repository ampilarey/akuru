<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListArabicReferenceAction;
use App\Domains\Courses\Actions\ListCourseActivitiesAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\Course;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogActivityController extends Controller
{
    public function index(Request $request, int $course): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $courseModel = Course::query()->findOrFail($course);

        return Inertia::render('Courses/Catalog/Activities', [
            'course' => [
                'id' => $courseModel->id,
                'title' => $courseModel->title,
            ],
            'activities' => app(ListCourseActivitiesAction::class)->execute($courseModel)->values(),
            'patterns' => ['selection', 'text_input', 'arrange', 'teacher_marked'],
            'skills' => ['listening', 'speaking', 'reading', 'writing'],
            ...app(ListArabicReferenceAction::class)->execute(activeOnly: true),
        ]);
    }

    public function store(Request $request, int $course): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        Course::query()->findOrFail($course);
        app(SaveActivityAction::class)->execute($this->payload($request, $course) + [
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('catalog.courses.activities.index', $course)
            ->with('success', 'Activity saved.');
    }

    public function update(Request $request, int $course, int $activity): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        Course::query()->findOrFail($course);
        $model = Activity::query()->where('course_id', $course)->findOrFail($activity);
        app(SaveActivityAction::class)->execute($this->payload($request, $course), $model);

        return redirect()->route('catalog.courses.activities.index', $course)
            ->with('success', 'Activity updated.');
    }

    public function destroy(Request $request, int $course, int $activity): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        Course::query()->findOrFail($course);
        Activity::query()->where('course_id', $course)->findOrFail($activity)->delete();

        return redirect()->route('catalog.courses.activities.index', $course)
            ->with('success', 'Activity deleted.');
    }

    public function export(Request $request, int $course): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $courseModel = Course::query()->findOrFail($course);
        $rows = app(ListCourseActivitiesAction::class)->execute($courseModel);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'title', 'pattern', 'activity_type', 'max_score', 'passing_score', 'is_required']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['id'],
                    $row['title'],
                    $row['pattern'],
                    $row['activity_type'],
                    $row['max_score'],
                    $row['passing_score'],
                    $row['is_required'] ? 'yes' : 'no',
                ]);
            }
            fclose($handle);
        }, 'course-'.$course.'-activities.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, int $courseId): array
    {
        $data = $request->input('data');
        if (is_string($data)) {
            $data = json_decode($data, true) ?: [];
        }

        $settings = $request->input('settings');
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?: [];
        }
        if (! is_array($settings)) {
            $settings = [];
        }
        foreach (['skill', 'letter_id', 'harakah_id'] as $key) {
            if ($request->filled($key)) {
                $settings[$key] = $request->input($key);
            }
        }

        return [
            'course_id' => $courseId,
            'course_module_id' => $request->filled('course_module_id') ? (int) $request->input('course_module_id') : null,
            'lesson_id' => $request->filled('lesson_id') ? (int) $request->input('lesson_id') : null,
            'title' => (string) $request->input('title', ''),
            'description' => $request->input('description'),
            'pattern' => (string) $request->input('pattern', ''),
            'activity_type' => (string) $request->input('activity_type', ''),
            'data' => is_array($data) ? $data : [],
            'settings' => is_array($settings) ? $settings : [],
            'max_score' => $request->filled('max_score') ? (int) $request->input('max_score') : 1,
            'passing_score' => $request->filled('passing_score') ? (int) $request->input('passing_score') : null,
            'is_required' => $request->boolean('is_required'),
        ];
    }
}
