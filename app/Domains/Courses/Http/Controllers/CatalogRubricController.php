<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListCourseRubricsAction;
use App\Domains\Courses\Actions\SaveRubricAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\Rubric;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A course's marking rubrics (Moodle parity slice M2, STATUS §5oi): build
 * them, and say which teacher-marked activities and assessments they mark.
 */
class CatalogRubricController extends Controller
{
    public function index(Request $request, int $course): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render('Courses/Catalog/Rubrics', app(ListCourseRubricsAction::class)->execute(Course::query()->findOrFail($course)) + [
            't' => Phrases::once('teach'),
        ]);
    }

    public function store(Request $request, int $course): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveRubricAction::class)->execute(Course::query()->findOrFail($course), $this->validated($request), $request->user()?->id);

        return redirect()->route('catalog.courses.rubrics.index', $course)->with('success', __('teach.rubric_saved'));
    }

    public function update(Request $request, int $course, int $rubric): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(SaveRubricAction::class)->execute(
            Course::query()->findOrFail($course),
            $this->validated($request),
            $request->user()?->id,
            Rubric::query()->where('course_id', $course)->findOrFail($rubric),
        );

        return redirect()->route('catalog.courses.rubrics.index', $course)->with('success', __('teach.rubric_saved'));
    }

    public function destroy(Request $request, int $course, int $rubric): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        // Items using it fall back to a typed score (the key is nullOnDelete);
        // marks already given keep their snapshot.
        Rubric::query()->where('course_id', $course)->findOrFail($rubric)->delete();

        return redirect()->route('catalog.courses.rubrics.index', $course)->with('success', __('teach.rubric_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'criteria' => ['required', 'array', 'max:'.SaveRubricAction::MAX_CRITERIA],
            'criteria.*.id' => ['nullable', 'string', 'max:32'],
            'criteria.*.title' => ['nullable', 'string', 'max:255'],
            'criteria.*.levels' => ['nullable', 'array', 'max:'.SaveRubricAction::MAX_LEVELS],
            'criteria.*.levels.*.id' => ['nullable', 'string', 'max:32'],
            'criteria.*.levels.*.label' => ['nullable', 'string', 'max:255'],
            'criteria.*.levels.*.points' => ['nullable', 'integer', 'min:0', 'max:'.SaveRubricAction::MAX_POINTS],
            'activity_ids' => ['nullable', 'array'],
            'activity_ids.*' => ['integer'],
            'assessment_ids' => ['nullable', 'array'],
            'assessment_ids.*' => ['integer'],
        ]);
    }
}
