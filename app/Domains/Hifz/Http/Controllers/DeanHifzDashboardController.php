<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Hifz\Services\HifzReportService;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\ListStudentIdsOnTheRollAction;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DeanHifzDashboardController extends Controller
{
    public function __construct(protected HifzReportService $reports) {}

    public function index(): View
    {
        abort_unless(auth()->user()->isHifzDean() || auth()->user()->isAdminLevel(), 403);

        $cards = [
            // Enrolments do not end when a pupil leaves the Institute — the
            // status vocabulary has no word for it and no screen can change it
            // — so "active students" asked of enrolments alone counted people
            // who had gone. The school's roll is the authority on who is still
            // a pupil; the enrolment says which of them is in a halaqa.
            //
            // Asked through People's own action rather than by importing its
            // Student model (rule 3), which also means "on the roll" is read
            // from the one place that defines it.
            //
            // It now counts distinct pupils rather than enrolment rows. The
            // card says "active students" and a pupil enrolled in two
            // programmes is one student, not two.
            'active_students' => count(app(ListStudentIdsOnTheRollAction::class)->execute(
                HifzEnrollment::where('status', 'active')->pluck('student_id')
            )),
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
