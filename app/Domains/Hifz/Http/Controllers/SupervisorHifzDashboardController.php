<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Hifz\Services\HifzReportService;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SupervisorHifzDashboardController extends Controller
{
    public function __construct(
        protected HifzScopeService $scope,
        protected HifzReportService $reports,
    ) {}

    public function index(): View
    {
        $user = auth()->user();
        $programIds = $this->scope->assignedProgramIds($user);
        $studentIds = $this->scope->assignedStudentIds($user);

        $cards = [
            'programs' => HifzProgram::whereIn('id', $programIds)->count(),
            'teachers' => HifzEnrollment::whereIn('hifz_program_id', $programIds)->distinct('teacher_id')->count('teacher_id'),
            'sessions_today' => HifzSession::whereIn('hifz_program_id', $programIds)->whereDate('session_date', today())->count(),
            'pending_review' => $this->reports->pendingSupervisorReviews($programIds),
            'missing_teachers' => $this->reports->teachersMissingTodayRecords($programIds)->count(),
            'needs_supervisor' => HifzSessionRecord::whereIn('hifz_program_id', $programIds)->where('requires_supervisor_review', true)->whereNull('reviewed_at')->count(),
            'parent_attention' => HifzSessionRecord::whereIn('student_id', $studentIds)->where('requires_parent_attention', true)->where('created_at', '>=', now()->subDays(7))->count(),
        ];

        $harakaLeaders = $this->reports->harakaMistakeLeaders($studentIds);
        $weakStudents = $this->reports->weakStudents($studentIds);
        $pendingMilestones = HifzMilestone::whereIn('hifz_program_id', $programIds)->where('status', 'pending')->with('student.user')->latest()->take(10)->get();
        $programs = HifzProgram::whereIn('id', $programIds)->with('supervisor')->get();

        return view('hifz.dashboard.supervisor', compact('cards', 'harakaLeaders', 'weakStudents', 'pendingMilestones', 'programs'));
    }
}
