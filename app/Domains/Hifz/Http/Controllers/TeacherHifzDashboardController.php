<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The teacher's Hifz dashboard: a roll-up only, since F5 moved session
 * recording to the engine's schedule. Inertia since the Hifz port's
 * second slice (STATUS §5jw).
 */
class TeacherHifzDashboardController extends Controller
{
    public function __construct(protected HifzScopeService $scope) {}

    public function index(): Response
    {
        $user = auth()->user();
        $teacher = $user->teacher;
        abort_unless($teacher, 403);

        $programs = HifzProgram::whereIn('id', $this->scope->assignedProgramIds($user))->get();
        $assigned = HifzEnrollment::where('teacher_id', $teacher->id)->where('status', 'active')->count();
        $todaySession = HifzSession::where('teacher_id', $teacher->id)->whereDate('session_date', today())->exists();

        return Inertia::render('Hifz/TeacherDashboard', [
            'assigned_students' => $assigned,
            'programs' => $programs->map(fn (HifzProgram $program): array => ['id' => $program->id, 'name' => $program->name])->values()->all(),
            'today_session' => $todaySession,
            'schedule_href' => route('teach.schedule'),
            't' => trans('admin'),
        ]);
    }
}
