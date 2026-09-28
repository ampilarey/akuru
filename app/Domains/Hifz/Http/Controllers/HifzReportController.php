<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Hifz\Services\HifzReportService;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Domains\Hifz\Support\HifzDashboardRows;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Hifz reports (the Hifz port, slice 3, STATUS §5jx): a hub of doors
 * and five reports, each an Inertia page. Four of them are the same shape
 * — a name, a figure or a date, sometimes a note — and share one page;
 * the milestone report is a paged table. Gated on `view_hifz_reports`,
 * scoped to the caller's pupils or programmes unless they are the dean.
 */
class HifzReportController extends Controller
{
    public function __construct(
        protected HifzReportService $reports,
        protected HifzScopeService $scope,
    ) {}

    public function index(): Response
    {
        abort_unless(auth()->user()->can('view_hifz_reports'), 403);

        return Inertia::render('Hifz/Reports', [
            'reports' => [
                ['key' => 'weak_students', 'href' => route('hifz.reports.weak-students')],
                ['key' => 'haraka', 'href' => route('hifz.reports.haraka-mistakes')],
                ['key' => 'parent_follow_up', 'href' => route('hifz.reports.parent-follow-up')],
                ['key' => 'teacher_completion', 'href' => route('hifz.reports.teacher-completion')],
                ['key' => 'milestones', 'href' => route('hifz.reports.milestones')],
            ],
            'export_href' => auth()->user()->can('export_hifz_reports') ? route('hifz.reports.export', ['type' => 'sessions']) : null,
            't' => trans('admin'),
        ]);
    }

    public function weakStudents(): Response
    {
        abort_unless(auth()->user()->can('view_hifz_reports'), 403);

        $rows = $this->reports->weakStudents($this->studentIds());

        return $this->rows('weak_students', HifzDashboardRows::weakStudents($rows), 'weak_count', 'hifz_weak_unit');
    }

    public function harakaMistakes(): Response
    {
        abort_unless(auth()->user()->can('view_hifz_reports'), 403);

        $rows = $this->reports->harakaMistakeLeaders($this->studentIds());

        return $this->rows('haraka', HifzDashboardRows::harakaLeaders($rows), 'total_haraka', null, 'danger');
    }

    public function parentFollowUp(): Response
    {
        abort_unless(auth()->user()->can('view_hifz_reports'), 403);

        $records = $this->reports->parentAttentionCases($this->studentIds());

        return $this->rows('parent_follow_up', HifzDashboardRows::parentCases($records), 'date');
    }

    public function teacherCompletion(): Response
    {
        abort_unless(auth()->user()->can('view_hifz_reports'), 403);

        $missing = $this->reports->teachersMissingTodayRecords($this->programIds());

        return $this->rows('teacher_completion', HifzDashboardRows::teachers($missing));
    }

    public function milestones(): Response
    {
        abort_unless(auth()->user()->can('view_hifz_reports'), 403);

        $query = HifzMilestone::with(['student.user', 'program'])->latest();
        if (! auth()->user()->isHifzDean()) {
            $query->whereIn('hifz_program_id', $this->scope->assignedProgramIds(auth()->user()));
        }

        return Inertia::render('Hifz/ReportMilestones', [
            'milestones' => $query->paginate(30)->through(fn (HifzMilestone $milestone): array => HifzDashboardRows::milestoneRow($milestone)),
            't' => trans('admin'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(auth()->user()->can('export_hifz_reports'), 403);

        $type = $request->get('type', 'sessions');

        return response()->streamDownload(function () use ($type) {
            $handle = fopen('php://output', 'w');

            if ($type === 'sessions') {
                Csv::put($handle, ['Date', 'Program', 'Teacher', 'Status']);
                HifzSession::with(['program', 'teacher.user'])->latest('session_date')->take(500)->each(function ($s) use ($handle) {
                    Csv::put($handle, [$s->session_date, $s->program->name, $s->teacher?->full_name, $s->status->value]);
                });
            }

            fclose($handle);
        }, "hifz-{$type}-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * The one page four reports share: which report (its title and empty
     * line are keys off it), the rows, which field is the figure beside the
     * name, the unit key that wraps it, and whether it reads as a warning.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function rows(string $report, array $rows, ?string $figure = null, ?string $unit = null, ?string $tone = null): Response
    {
        return Inertia::render('Hifz/ReportRows', [
            'report' => $report,
            'rows' => $rows,
            'figure' => $figure,
            'unit' => $unit,
            'tone' => $tone,
            'back_href' => route('hifz.reports.index'),
            't' => trans('admin'),
        ]);
    }

    private function studentIds(): ?Collection
    {
        return auth()->user()->isHifzDean() ? null : $this->scope->assignedStudentIds(auth()->user());
    }

    private function programIds(): ?Collection
    {
        return auth()->user()->isHifzDean() ? null : $this->scope->assignedProgramIds(auth()->user());
    }
}
