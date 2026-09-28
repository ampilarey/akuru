<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Domains\Hifz\Support\HifzDashboardRows;
use App\Enums\Hifz\HifzMilestoneStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hifz\StoreHifzMilestoneRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HifzMilestoneController extends Controller
{
    public function __construct(protected HifzScopeService $scope) {}

    public function index(): Response
    {
        $this->authorize('viewAny', HifzMilestone::class);

        $user = auth()->user();
        $query = HifzMilestone::with(['student.user', 'program'])->latest();

        // Scoped by pupil, not by programme: a parent or a pupil in a halaqa
        // used to see every child's milestones in it (STATUS §5ij, found
        // when the parent role gained its real grants by migration and
        // `HifzCrossRoleAccessTest` could finally open the page). A teacher's
        // and a supervisor's pupils are the ones assigned to them.
        if (! $user->isHifzDean()) {
            $query->whereIn('student_id', $this->scope->assignedStudentIds($user));
        }

        // The Hifz port, slice 3 (STATUS §5jx): the list is an Inertia page.
        // Review shows on a pending row for whoever the policy lets review
        // it; Approve on a reviewed row for the dean — as the Blade did by
        // role, now by the same policy the buttons post through.
        $milestones = $query->paginate(20)->through(fn (HifzMilestone $milestone): array => [
            ...HifzDashboardRows::milestoneRow($milestone),
            'can_review' => $milestone->status === HifzMilestoneStatus::Pending && $user->can('review', $milestone),
            'can_approve' => $milestone->status === HifzMilestoneStatus::SupervisorReviewed && $user->can('approve', $milestone),
        ]);

        return Inertia::render('Hifz/Milestones', [
            'milestones' => $milestones,
            't' => trans('admin'),
        ]);
    }

    public function store(StoreHifzMilestoneRequest $request): RedirectResponse
    {
        $teacher = auth()->user()->teacher;

        HifzMilestone::create([
            ...$request->validated(),
            'teacher_id' => $teacher?->id,
            'completed_at' => now(),
            'recommended_by' => auth()->id(),
            'recommended_at' => now(),
            'status' => HifzMilestoneStatus::Pending,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', trans('admin.hifz_flash_milestone_recommended'));
    }

    public function supervisorReview(HifzMilestone $milestone): RedirectResponse
    {
        $this->authorize('review', $milestone);

        $milestone->update([
            'status' => HifzMilestoneStatus::SupervisorReviewed,
            'supervisor_reviewed_by' => auth()->id(),
            'supervisor_reviewed_at' => now(),
            'supervisor_id' => auth()->id(),
        ]);

        return back()->with('success', trans('admin.hifz_flash_milestone_reviewed'));
    }

    public function approve(HifzMilestone $milestone): RedirectResponse
    {
        $this->authorize('approve', $milestone);

        $milestone->update([
            'status' => HifzMilestoneStatus::Approved,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', trans('admin.hifz_flash_milestone_approved'));
    }

    public function reject(HifzMilestone $milestone): RedirectResponse
    {
        $this->authorize('approve', $milestone);

        $milestone->update([
            'status' => HifzMilestoneStatus::Rejected,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', trans('admin.hifz_flash_milestone_rejected'));
    }
}
