<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Enums\Hifz\HifzMilestoneStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hifz\StoreHifzMilestoneRequest;
use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Hifz\Services\HifzScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HifzMilestoneController extends Controller
{
    public function __construct(protected HifzScopeService $scope) {}

    public function index(): View
    {
        $this->authorize('viewAny', HifzMilestone::class);

        $user = auth()->user();
        $query = HifzMilestone::with(['student.user', 'program'])->latest();

        if (! $user->isHifzDean() && ! $user->isAdminLevel()) {
            $query->whereIn('hifz_program_id', $this->scope->assignedProgramIds($user));
        }

        $milestones = $query->paginate(20);

        return view('hifz.milestones.index', compact('milestones'));
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

        return back()->with('success', 'Milestone recommended for review.');
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

        return back()->with('success', 'Milestone reviewed and sent to dean.');
    }

    public function approve(HifzMilestone $milestone): RedirectResponse
    {
        $this->authorize('approve', $milestone);

        $milestone->update([
            'status' => HifzMilestoneStatus::Approved,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Milestone approved.');
    }

    public function reject(HifzMilestone $milestone): RedirectResponse
    {
        $this->authorize('approve', $milestone);

        $milestone->update([
            'status' => HifzMilestoneStatus::Rejected,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Milestone rejected.');
    }
}
