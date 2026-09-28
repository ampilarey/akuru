<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Models\HifzSessionRecord;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Domains\Hifz\Support\HifzDashboardRows;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A parent's view of a child's Hifz: today's record, the week, the
 * approved milestones; a picker when there is more than one child.
 * Inertia since the Hifz port's second slice (STATUS §5jw); the scope
 * check on the chosen child is as it was.
 */
class ParentHifzDashboardController extends Controller
{
    public function __construct(protected HifzScopeService $scope) {}

    public function index(Request $request): Response
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
        $milestones = HifzMilestone::where('student_id', $selectedChild->id)->where('status', 'approved')->latest()->get();

        return Inertia::render('Hifz/ParentDashboard', [
            'children' => $children->map(fn ($child): array => ['id' => (int) $child->id, 'name' => (string) $child->full_name])->values()->all(),
            'selected_child' => ['id' => (int) $selectedChild->id, 'name' => (string) $selectedChild->full_name],
            'today' => $todayRecord === null ? null : HifzDashboardRows::records(collect([$todayRecord]))[0],
            'week' => HifzDashboardRows::records($weeklyRecords),
            'milestones' => HifzDashboardRows::milestones($milestones),
            't' => trans('admin'),
        ]);
    }
}
