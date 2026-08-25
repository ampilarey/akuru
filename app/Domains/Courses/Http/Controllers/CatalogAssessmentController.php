<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\AttachAssessmentQuestionAction;
use App\Domains\Courses\Actions\ListCourseAssessmentsAction;
use App\Domains\Courses\Actions\ListQuestionsAction;
use App\Domains\Courses\Actions\SaveAssessmentAction;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\Course;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogAssessmentController extends Controller
{
    public function index(Request $request, int $course): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $courseModel = Course::query()->findOrFail($course);

        return Inertia::render('Courses/Catalog/Assessments', [
            'course' => ['id' => $courseModel->id, 'title' => $courseModel->title],
            'assessments' => app(ListCourseAssessmentsAction::class)->execute($courseModel)->values(),
            'questions' => app(ListQuestionsAction::class)->execute()->values(),
            'types' => [
                'lesson_quiz', 'module_test', 'placement_test', 'final_exam',
                'listening', 'speaking', 'reading', 'writing', 'practical', 'mixed', 'assignment',
            ],
        ]);
    }

    public function store(Request $request, int $course): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        Course::query()->findOrFail($course);
        app(SaveAssessmentAction::class)->execute($this->payload($request, $course) + [
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('catalog.courses.assessments.index', $course)
            ->with('success', 'Assessment saved.');
    }

    public function update(Request $request, int $course, int $assessment): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        Course::query()->findOrFail($course);
        $model = Assessment::query()->where('course_id', $course)->findOrFail($assessment);
        app(SaveAssessmentAction::class)->execute($this->payload($request, $course), $model);

        return redirect()->route('catalog.courses.assessments.index', $course)
            ->with('success', 'Assessment updated.');
    }

    public function attach(Request $request, int $course, int $assessment): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        Course::query()->findOrFail($course);
        Assessment::query()->where('course_id', $course)->findOrFail($assessment);
        app(AttachAssessmentQuestionAction::class)->execute([
            'assessment_id' => $assessment,
            'question_id' => (int) $request->input('question_id'),
            'points_override' => $request->filled('points_override') ? (int) $request->input('points_override') : null,
            'is_required' => $request->boolean('is_required', true),
        ]);

        return back()->with('success', 'Question attached.');
    }

    public function detach(Request $request, int $course, int $assessment, int $question): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        Course::query()->findOrFail($course);
        Assessment::query()->where('course_id', $course)->findOrFail($assessment);
        app(AttachAssessmentQuestionAction::class)->detach($assessment, $question);

        return back()->with('success', 'Question removed.');
    }

    public function export(Request $request, int $course): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $courseModel = Course::query()->findOrFail($course);
        $rows = app(ListCourseAssessmentsAction::class)->execute($courseModel);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'title', 'assessment_type', 'status', 'max_score', 'retake_limit']);
            foreach ($rows as $row) {
                fputcsv($handle, [$row['id'], $row['title'], $row['assessment_type'], $row['status'], $row['max_score'], $row['retake_limit']]);
            }
            fclose($handle);
        }, 'course-'.$course.'-assessments.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, int $courseId): array
    {
        return [
            'course_id' => $courseId,
            'course_module_id' => $request->filled('course_module_id') ? (int) $request->input('course_module_id') : null,
            'lesson_id' => $request->filled('lesson_id') ? (int) $request->input('lesson_id') : null,
            'title' => (string) $request->input('title', ''),
            'description' => $request->input('description'),
            'assessment_type' => (string) $request->input('assessment_type', 'lesson_quiz'),
            'status' => (string) $request->input('status', 'draft'),
            'time_limit_minutes' => $request->filled('time_limit_minutes') ? (int) $request->input('time_limit_minutes') : null,
            'passing_score' => $request->filled('passing_score') ? (int) $request->input('passing_score') : null,
            'retake_limit' => $request->filled('retake_limit') ? (int) $request->input('retake_limit') : null,
            'randomize_questions' => $request->boolean('randomize_questions'),
            'show_results' => $request->boolean('show_results', true),
            'show_correct_answers' => $request->boolean('show_correct_answers'),
            'requires_teacher_marking' => $request->boolean('requires_teacher_marking'),
        ];
    }
}
