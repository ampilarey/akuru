<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\AttachAssessmentQuestionAction;
use App\Domains\Courses\Actions\ListCourseAssessmentsAction;
use App\Domains\Courses\Actions\ListQuestionsAction;
use App\Domains\Courses\Actions\ReorderAssessmentQuestionsAction;
use App\Domains\Courses\Actions\SaveAssessmentAction;
use App\Domains\Courses\Enums\AssessmentStatus;
use App\Domains\Courses\Enums\AssessmentType;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\Course;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            // SPEC §19's eleven types, served from the enum that now owns
            // them. This was a hardcoded array here, and nothing validated
            // against it — so the list was advisory and any string was
            // storable.
            'types' => AssessmentType::options(),
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

    /**
     * SPEC §21's **Position**. `BuildAssessmentSnapshotsAction` has always
     * ordered attempts by it and nothing could ever change it, so the order
     * questions were attached in was the order every student sat them in.
     */
    public function reorderQuestions(Request $request, int $course, int $assessment): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        Course::query()->findOrFail($course);
        Assessment::query()->where('course_id', $course)->findOrFail($assessment);

        $data = $request->validate([
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer'],
        ]);

        app(ReorderAssessmentQuestionsAction::class)->execute($assessment, $data['question_ids']);

        return back()->with('success', 'Question order saved.');
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
    /**
     * CLAUDE.md rule 5: "Thin controllers. authorize → **validate into DTO** →
     * call Action."
     *
     * This method authorized and then built an array entirely out of
     * `$request->input()` / `boolean()` / `filled()` — **no `validate()` call
     * anywhere on the assessment save path**. So a negative time limit, a
     * negative retake limit, an arbitrary `assessment_type`, an arbitrary
     * `status`, and a module or lesson from a different course all reached the
     * Action, and whatever it did not reject was stored.
     *
     * Deliberately **not** validated: `passing_score` against `max_score`.
     * Legacy rows carry a passing score expressed as a percentage on a
     * small-max quiz, and `TeacherReviewReportTest` pins a reader that treats
     * `passing_score > max_score` as a percent on purpose. Forbidding it here
     * would break that reading, and choosing between the two meanings is a
     * decision, not a cleanup.
     *
     * @return array<string, mixed>
     */
    private function payload(Request $request, int $courseId): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assessment_type' => ['nullable', Rule::enum(AssessmentType::class)],
            'status' => ['nullable', Rule::enum(AssessmentStatus::class)],
            'course_module_id' => ['nullable', 'integer', 'exists:course_modules,id'],
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            // A zero or negative time limit is not "no limit" — it is an
            // assessment that is over before it starts.
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'passing_score' => ['nullable', 'integer', 'min:0'],
            'retake_limit' => ['nullable', 'integer', 'min:0'],
            'randomize_questions' => ['nullable', 'boolean'],
            'show_results' => ['nullable', 'boolean'],
            'show_correct_answers' => ['nullable', 'boolean'],
            'requires_teacher_marking' => ['nullable', 'boolean'],
        ]);

        return [
            'course_id' => $courseId,
            'course_module_id' => $data['course_module_id'] ?? null,
            'lesson_id' => $data['lesson_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assessment_type' => $data['assessment_type'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
            'passing_score' => $data['passing_score'] ?? null,
            'retake_limit' => $data['retake_limit'] ?? null,
            'randomize_questions' => $request->boolean('randomize_questions'),
            'show_results' => $request->boolean('show_results', true),
            'show_correct_answers' => $request->boolean('show_correct_answers'),
            'requires_teacher_marking' => $request->boolean('requires_teacher_marking'),
        ];
    }
}
