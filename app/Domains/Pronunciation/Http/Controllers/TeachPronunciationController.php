<?php

namespace App\Domains\Pronunciation\Http\Controllers;

use App\Domains\Pronunciation\Actions\ListPronunciationQueuesAction;
use App\Domains\Pronunciation\Actions\ReviewPronunciationAttemptAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * §51.16 steps 3–5: the teacher's ear. Staff only — students never see
 * other students' recordings.
 */
class TeachPronunciationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasAnyRole(['super_admin', 'admin', 'teacher', 'supervisor', 'dean']), 403);
        $queues = app(ListPronunciationQueuesAction::class)->execute();

        return Inertia::render('Pronunciation/Teach', [
            'review_queue' => $queues['review_queue'],
            'letters' => $queues['letters'],
            'harakas' => $queues['harakas'],
            'ai_enabled' => $queues['ai_enabled'],
        ]);
    }

    public function review(Request $request, int $attempt): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(['super_admin', 'admin', 'teacher', 'supervisor', 'dean']), 403);
        $data = $request->validate([
            'verified_letter_id' => 'nullable|integer|exists:arabic_letters,id',
            'verified_haraka_id' => 'nullable|integer|exists:arabic_harakas,id',
            'reject' => 'nullable|boolean',
            'rejection_reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500',
        ]);

        app(ReviewPronunciationAttemptAction::class)->execute($attempt, (int) $request->user()->id, $data);

        return back()->with('success', 'Attempt reviewed.');
    }
}
