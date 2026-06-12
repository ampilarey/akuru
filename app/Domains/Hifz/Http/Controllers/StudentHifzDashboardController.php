<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class StudentHifzDashboardController extends Controller
{
    public function __construct(protected HifzScopeService $scope) {}

    public function index(): View
    {
        $student = auth()->user()->student;
        abort_unless($student, 403);

        $enrollment = HifzEnrollment::where('student_id', $student->id)->where('status', 'active')->with('program', 'currentSurah')->first();
        $recentRecords = HifzSessionRecord::where('student_id', $student->id)->with('session')->latest()->take(10)->get();
        $milestones = HifzMilestone::where('student_id', $student->id)->where('status', 'approved')->latest()->get();
        $weakRecords = HifzSessionRecord::where('student_id', $student->id)->where('overall_status', 'needs_revision')->latest()->take(5)->get();

        return view('hifz.dashboard.student', compact('student', 'enrollment', 'recentRecords', 'milestones', 'weakRecords'));
    }
}
