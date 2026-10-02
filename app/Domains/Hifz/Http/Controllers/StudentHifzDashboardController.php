<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Domains\Hifz\Support\HifzDashboardRows;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A pupil's own Hifz progress: where they are, their recent sessions,
 * their approved milestones. Inertia since the Hifz port's second slice
 * (STATUS §5jw).
 */
class StudentHifzDashboardController extends Controller
{
    public function __construct(protected HifzScopeService $scope) {}

    public function index(): Response
    {
        $student = auth()->user()->student;
        abort_unless($student, 403);

        $enrollment = HifzEnrollment::where('student_id', $student->id)->where('status', 'active')->with('program')->first();
        $recentRecords = HifzSessionRecord::where('student_id', $student->id)->with('session')->latest()->take(10)->get();
        $milestones = HifzMilestone::where('student_id', $student->id)->where('status', 'approved')->latest()->get();

        return Inertia::render('Hifz/StudentDashboard', [
            'enrollment' => $enrollment === null ? null : [
                'program' => $enrollment->program?->name,
                'current_juz' => $enrollment->current_juz,
                'current_page' => $enrollment->current_page,
            ],
            'next_target' => $recentRecords->first()?->next_target,
            'recent_records' => HifzDashboardRows::records($recentRecords),
            'milestones' => HifzDashboardRows::milestones($milestones),
            't' => Phrases::once('admin'),
        ]);
    }
}
