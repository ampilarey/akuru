<?php

namespace App\Domains\Pronunciation\Http\Controllers;

use App\Domains\Pronunciation\Actions\ListPronunciationQueuesAction;
use App\Domains\Pronunciation\Actions\ReviewPronunciationAttemptAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * §51.16 steps 3–5: the teacher's ear. Staff only — students never see
 * other students' recordings.
 *
 * The queue reads the `teach` book and says what was saved in the page's
 * language (STATUS §5pr).
 */
class TeachPronunciationController extends Controller
{
    /**
     * The teaching staff, as every teaching screen's door names them
     * (`NavigationMap::STAFF`). This list said `dean`, and no role is called
     * that: the dean is `headmaster`, whom the screens label *Dean*. So the
     * dean was refused the queue the list was written to let them into
     * (STATUS §5ps); `RoleNamesExistTest` now holds every role a gate names.
     */
    private const STAFF_ROLES = ['super_admin', 'admin', 'headmaster', 'supervisor', 'teacher'];

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasAnyRole(self::STAFF_ROLES), 403);
        $queues = app(ListPronunciationQueuesAction::class)->execute();

        return Inertia::render('Pronunciation/Teach', [
            'review_queue' => $queues['review_queue'],
            'letters' => $queues['letters'],
            'harakas' => $queues['harakas'],
            'ai_enabled' => $queues['ai_enabled'],
            't' => Phrases::once('teach'),
        ]);
    }

    public function review(Request $request, int $attempt): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(self::STAFF_ROLES), 403);
        $data = $request->validate([
            'verified_letter_id' => 'nullable|integer|exists:arabic_letters,id',
            'verified_haraka_id' => 'nullable|integer|exists:arabic_harakas,id',
            'reject' => 'nullable|boolean',
            'rejection_reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500',
        ]);

        app(ReviewPronunciationAttemptAction::class)->execute($attempt, (int) $request->user()->id, $data);

        return back()->with('success', __('teach.flash_pron_attempt_reviewed'));
    }
}
