<?php

namespace App\Http\Controllers\Hifz;

use App\Http\Controllers\Controller;
use App\Models\HifzEnrollment;
use App\Models\HifzProgram;
use App\Models\HifzSession;
use App\Services\Hifz\HifzScopeService;
use Illuminate\View\View;

class TeacherHifzDashboardController extends Controller
{
    public function __construct(protected HifzScopeService $scope) {}

    public function index(): View
    {
        $user = auth()->user();
        $teacher = $user->teacher;

        abort_unless($teacher, 403);

        $programs = HifzProgram::whereIn('id', $this->scope->assignedProgramIds($user))->get();
        $enrollments = HifzEnrollment::where('teacher_id', $teacher->id)
            ->where('status', 'active')
            ->with('student.user', 'program')
            ->get();

        $todaySession = HifzSession::where('teacher_id', $teacher->id)
            ->whereDate('session_date', today())
            ->with('records')
            ->first();

        return view('hifz.dashboard.teacher', compact('programs', 'enrollments', 'todaySession'));
    }
}
