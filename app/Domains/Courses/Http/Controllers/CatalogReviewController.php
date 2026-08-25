<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListReviewQueueAction;
use App\Domains\Progress\Actions\ReviewAttemptAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogReviewController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render('Courses/Catalog/Reviews', [
            'rows' => app(ListReviewQueueAction::class)->execute()->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(ReviewAttemptAction::class)->execute(
            (string) $request->input('kind'),
            (int) $request->input('attempt_id'),
            $request->only(['score', 'max_score', 'feedback', 'item_scores']),
            (int) $request->user()->id,
        );

        return redirect()->route('catalog.reviews.index')->with('success', 'Review saved.');
    }
}
