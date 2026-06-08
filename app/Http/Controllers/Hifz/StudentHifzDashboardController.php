<?php

namespace App\Http\Controllers\Hifz;

use App\Http\Controllers\Controller;
use App\Models\HifzEnrollment;
use App\Models\HifzMilestone;
use App\Models\HifzSessionRecord;
use App\Services\Hifz\HifzScopeService;
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
