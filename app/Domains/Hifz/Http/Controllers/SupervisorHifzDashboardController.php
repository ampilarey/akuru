<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Hifz\Services\HifzReportService;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Domains\Hifz\Support\HifzDashboardRows;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The supervisor's Hifz dashboard, scoped to their programmes. Inertia
 * since the Hifz port's second slice (STATUS §5jw): the seven cards, the
 * haraka alerts and weak students, the pending milestones by pupil with
 * their Review, and the two doors.
 */
class SupervisorHifzDashboardController extends Controller
{
    public function __construct(
        protected HifzScopeService $scope,
        protected HifzReportService $reports,
    ) {}

    public function index(): Response
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

        return Inertia::render('Hifz/SupervisorDashboard', [
            'cards' => $cards,
            'haraka_leaders' => HifzDashboardRows::harakaLeaders($this->reports->harakaMistakeLeaders($studentIds)->take(5)),
            'weak_students' => HifzDashboardRows::weakStudents($this->reports->weakStudents($studentIds)->take(5)),
            'pending_milestones' => HifzDashboardRows::milestones(
                HifzMilestone::whereIn('hifz_program_id', $programIds)->where('status', 'pending')->with('student.user')->latest()->take(10)->get()
            ),
            'links' => ['reports' => route('hifz.reports.index'), 'milestones' => route('hifz.milestones.index')],
            't' => Phrases::once('admin'),
        ]);
    }
}
