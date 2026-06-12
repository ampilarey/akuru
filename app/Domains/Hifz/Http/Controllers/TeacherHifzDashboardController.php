<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Http\Controllers\Controller;
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
