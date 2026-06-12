<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Identity\Models\User;
use App\Domains\Hifz\Services\HifzReportService;
use Illuminate\View\View;

class DeanHifzDashboardController extends Controller
{
    public function __construct(protected HifzReportService $reports) {}

    public function index(): View
    {
        abort_unless(auth()->user()->isHifzDean() || auth()->user()->isAdminLevel(), 403);

        $cards = [
            'active_students' => HifzEnrollment::where('status', 'active')->count(),
            'active_programs' => HifzProgram::where('status', 'active')->count(),
            'supervisors' => User::role('supervisor')->count(),
            'teachers' => HifzEnrollment::where('status', 'active')->distinct('teacher_id')->count('teacher_id'),
            'sessions_today' => HifzSession::whereDate('session_date', today())->count(),
            'pending_supervisor_review' => $this->reports->pendingSupervisorReviews(),
            'absent_today' => HifzSessionRecord::whereHas('session', fn ($q) => $q->whereDate('session_date', today()))
                ->where('attendance_status', 'absent')->count(),
            'parent_attention' => HifzSessionRecord::where('requires_parent_attention', true)->where('created_at', '>=', now()->subDays(7))->count(),
            'juz_this_month' => $this->reports->monthlyJuzCompletions(),
            'missing_teachers' => $this->reports->teachersMissingTodayRecords()->count(),
        ];

        $harakaLeaders = $this->reports->harakaMistakeLeaders();
        $weakStudents = $this->reports->weakStudents();
        $pendingMilestones = HifzMilestone::where('status', 'supervisor_reviewed')->with('student.user')->latest()->take(10)->get();

        return view('hifz.dashboard.dean', compact('cards', 'harakaLeaders', 'weakStudents', 'pendingMilestones'));
    }
}
