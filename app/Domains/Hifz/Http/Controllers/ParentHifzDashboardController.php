<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParentHifzDashboardController extends Controller
{
    public function __construct(protected HifzScopeService $scope) {}

    public function index(Request $request): View
    {
        $children = auth()->user()->schoolChildren()->with('user')->get();
        abort_unless($children->isNotEmpty(), 403);

        $selectedChild = $request->filled('student_id')
            ? $children->firstWhere('id', (int) $request->student_id)
            : $children->first();

        abort_unless($selectedChild && $this->scope->canAccessStudent(auth()->user(), $selectedChild), 403);

        $todayRecord = HifzSessionRecord::where('student_id', $selectedChild->id)
            ->whereHas('session', fn ($q) => $q->whereDate('session_date', today()))
            ->with('session')
            ->first();

        $weeklyRecords = HifzSessionRecord::where('student_id', $selectedChild->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->with('session')
            ->latest()
            ->get();

        $milestones = HifzMilestone::where('student_id', $selectedChild->id)
            ->where('status', 'approved')
            ->latest()
            ->get();

        return view('hifz.dashboard.parent', compact('children', 'selectedChild', 'todayRecord', 'weeklyRecords', 'milestones'));
    }
}
