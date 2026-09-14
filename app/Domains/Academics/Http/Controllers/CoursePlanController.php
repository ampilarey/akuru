<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\CopyPlanAction;
use App\Domains\Academics\Actions\ResolveTeacherIdForUserAction;
use App\Domains\Academics\Actions\SaveCoursePlanAction;
use App\Domains\Academics\Actions\SavePlanTopicAction;
use App\Domains\Academics\Enums\CoursePlanStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\CoursePlan;
use App\Domains\Academics\Models\Subject;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoursePlanController extends Controller
{
    /**
     * CLAUDE.md: *"every listing gets CSV export."*
     *
     * One row per plan, with how far through its topics it is — the question a
     * supervisor actually asks of this screen at the end of a term.
     *
     * **Carries `index()`'s scoping, not just its permission check.** A teacher
     * without `registers.manage` sees only their own plans on screen; an export
     * that quietly handed them everybody's would make the CSV a way around the
     * filter rather than a copy of it.
     */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('registers.fill') || $request->user()?->can('registers.manage'), 403);

        $canManage = (bool) $request->user()?->can('registers.manage');
        $teacherId = app(ResolveTeacherIdForUserAction::class)->execute($request->user()?->id);

        $plans = CoursePlan::query()
            ->with('topics')
            ->when(! $canManage && $teacherId, fn ($query) => $query->where('teacher_id', $teacherId))
            ->orderByDesc('id')
            ->get();

        $classes = ClassRoom::query()->pluck('name', 'id');
        $subjects = Subject::query()->pluck('name', 'id');
        $years = AcademicYear::query()->pluck('name', 'id');

        return response()->streamDownload(function () use ($plans, $classes, $subjects, $years): void {
            $handle = fopen('php://output', 'w');
            Csv::put($handle, ['id', 'title', 'subject', 'class', 'academic_year', 'status', 'topics', 'topics_completed']);

            foreach ($plans as $plan) {
                Csv::put($handle, [
                    $plan->id,
                    $plan->title,
                    $subjects[$plan->subject_id] ?? '',
                    $classes[$plan->classroom_id] ?? '',
                    $years[$plan->academic_year_id] ?? $plan->academic_year,
                    $plan->status?->value,
                    $plan->topics->count(),
                    $plan->topics->where('is_completed', true)->count(),
                ]);
            }

            fclose($handle);
        }, 'course-plans.csv', ['Content-Type' => 'text/csv']);
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('registers.fill') || $request->user()?->can('registers.manage'), 403);

        $canManage = (bool) $request->user()?->can('registers.manage');
        $teacherId = app(ResolveTeacherIdForUserAction::class)->execute($request->user()?->id);

        $plans = CoursePlan::query()
            ->with('topics')
            ->when(! $canManage && $teacherId, fn ($query) => $query->where('teacher_id', $teacherId))
            ->orderByDesc('id')
            ->get()
            ->map(fn (CoursePlan $plan) => [
                'id' => $plan->id,
                'title' => $plan->title,
                'teacher_id' => $plan->teacher_id,
                'subject_id' => $plan->subject_id,
                'classroom_id' => $plan->classroom_id,
                'academic_year_id' => $plan->academic_year_id,
                'academic_year' => $plan->academic_year,
                'status' => $plan->status?->value,
                'topics' => $plan->topics->map(fn ($topic) => [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'order' => $topic->order,
                    'is_completed' => $topic->is_completed,
                ]),
            ]);

        return Inertia::render('Academics/Plans/Index', [
            'plans' => $plans,
            'teacherId' => $teacherId,
            'canManage' => $canManage,
            'years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name']),
            'classes' => ClassRoom::query()->orderBy('name')->get(['id', 'name', 'section', 'academic_year_id']),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name', 'code']),
            'statuses' => array_map(fn (CoursePlanStatus $status) => $status->value, CoursePlanStatus::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('registers.fill') || $request->user()?->can('registers.manage'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'classroom_id' => ['required', 'integer', 'exists:classes,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::enum(CoursePlanStatus::class)],
        ]);

        $this->assertOwnsTeacher($request, (int) $data['teacher_id']);

        app(SaveCoursePlanAction::class)->execute($data);

        return redirect()->route('academics.plans.index')->with('success', 'Plan saved.');
    }

    public function storeTopic(Request $request, CoursePlan $coursePlan): RedirectResponse
    {
        abort_unless($request->user()?->can('registers.fill') || $request->user()?->can('registers.manage'), 403);
        $this->assertOwnsTeacher($request, (int) $coursePlan->teacher_id);

        app(SavePlanTopicAction::class)->execute($coursePlan, $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'order' => ['nullable', 'integer', 'min:1'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
        ]));

        return redirect()->route('academics.plans.index')->with('success', 'Topic added.');
    }

    public function copy(Request $request, CoursePlan $coursePlan): RedirectResponse
    {
        abort_unless($request->user()?->can('registers.fill') || $request->user()?->can('registers.manage'), 403);
        $this->assertOwnsTeacher($request, (int) $coursePlan->teacher_id);

        app(CopyPlanAction::class)->execute($coursePlan, $request->validate([
            'classroom_id' => ['required', 'integer', 'exists:classes,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ]));

        return redirect()->route('academics.plans.index')->with('success', 'Plan copied.');
    }

    private function assertOwnsTeacher(Request $request, int $teacherId): void
    {
        if ($request->user()?->can('registers.manage')) {
            return;
        }

        $own = app(ResolveTeacherIdForUserAction::class)->execute($request->user()?->id);
        abort_unless($own !== null && $own === $teacherId, 403);
    }
}
