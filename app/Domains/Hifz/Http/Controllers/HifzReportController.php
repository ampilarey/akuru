<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Hifz\Services\HifzReportService;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HifzReportController extends Controller
{
    public function __construct(
        protected HifzReportService $reports,
        protected HifzScopeService $scope,
    ) {}

    public function index(): View
    {
        abort_unless(auth()->user()->can('view_hifz_reports'), 403);

        return view('hifz.reports.index');
    }

    public function weakStudents(): View
    {
        abort_unless(auth()->user()->can('view_hifz_reports'), 403);

        $studentIds = auth()->user()->isHifzDean() ? null : $this->scope->assignedStudentIds(auth()->user());
        $rows = $this->reports->weakStudents($studentIds);

        return view('hifz.reports.weak-students', compact('rows'));
    }

    public function harakaMistakes(): View
    {
        abort_unless(auth()->user()->can('view_hifz_reports'), 403);

        $studentIds = auth()->user()->isHifzDean() ? null : $this->scope->assignedStudentIds(auth()->user());
        $rows = $this->reports->harakaMistakeLeaders($studentIds);

        return view('hifz.reports.haraka-mistakes', compact('rows'));
    }

    public function parentFollowUp(): View
    {
        abort_unless(auth()->user()->can('view_hifz_reports'), 403);

        $studentIds = auth()->user()->isHifzDean() ? null : $this->scope->assignedStudentIds(auth()->user());
        $records = $this->reports->parentAttentionCases($studentIds);

        return view('hifz.reports.parent-follow-up', compact('records'));
    }

    public function teacherCompletion(): View
    {
        abort_unless(auth()->user()->can('view_hifz_reports'), 403);

        $programIds = auth()->user()->isHifzDean() ? null : $this->scope->assignedProgramIds(auth()->user());
        $missing = $this->reports->teachersMissingTodayRecords($programIds);

        return view('hifz.reports.teacher-completion', compact('missing'));
    }

    public function milestones(): View
    {
        abort_unless(auth()->user()->can('view_hifz_reports'), 403);

        $query = HifzMilestone::with(['student.user', 'program'])->latest();
        if (! auth()->user()->isHifzDean()) {
            $query->whereIn('hifz_program_id', $this->scope->assignedProgramIds(auth()->user()));
        }

        $milestones = $query->paginate(30);

        return view('hifz.reports.milestones', compact('milestones'));
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(auth()->user()->can('export_hifz_reports'), 403);

        $type = $request->get('type', 'sessions');

        return response()->streamDownload(function () use ($type) {
            $handle = fopen('php://output', 'w');

            if ($type === 'sessions') {
                fputcsv($handle, ['Date', 'Program', 'Teacher', 'Status']);
                HifzSession::with(['program', 'teacher.user'])->latest('session_date')->take(500)->each(function ($s) use ($handle) {
                    fputcsv($handle, [$s->session_date, $s->program->name, $s->teacher?->full_name, $s->status->value]);
                });
            }

            fclose($handle);
        }, "hifz-{$type}-".now()->format('Y-m-d').'.csv');
    }
}
