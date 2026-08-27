<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\ListMyReviewAssignmentsAction;
use App\Domains\Library\Actions\SubmitResearchReviewAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L7 reviewer portal (§12.2) — new UI area, so Inertia. Reviewers see
 * ONLY their own assignments; ownership is enforced in the action.
 */
class ReviewerPortalController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Library/Review', [
            'assignments' => app(ListMyReviewAssignmentsAction::class)->execute((int) $request->user()->id),
        ]);
    }

    public function store(Request $request, int $assignment): RedirectResponse
    {
        $data = $request->validate([
            'recommendation' => 'required|in:accept,revise,reject',
            'comment' => 'nullable|string|max:5000',
        ]);

        app(SubmitResearchReviewAction::class)->execute(
            (int) $request->user()->id,
            $assignment,
            $data['recommendation'],
            $data['comment'] ?? null,
        );

        return back()->with('success', 'Review submitted.');
    }
}
