<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Notifications\Actions\ListUserNotificationsAction;
use App\Domains\Notifications\Actions\MarkUserNotificationsReadAction;
use App\Domains\Notifications\Actions\ResolveNotificationPreferencesAction;
use App\Domains\Notifications\Actions\SaveNotificationPreferencesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The notification centre, as a page a person can actually open.
 *
 * `/notifications` already existed and returns JSON that nothing calls; the
 * Blade view beside it was rendered by no route at all. This adds the missing
 * half rather than changing that endpoint, so any future API caller keeps
 * working.
 */
class PortalNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $this->userId($request);

        return Inertia::render('Portal/Notifications', [
            'notifications' => app(ListUserNotificationsAction::class)->execute($userId)->all(),
            // E22c: which categories reach me. Labels come from the action so
            // the page cannot offer a toggle for a category nothing sends.
            'categories' => ResolveNotificationPreferencesAction::CATEGORIES,
            'preferences' => app(ResolveNotificationPreferencesAction::class)->execute($userId),
        ]);
    }

    public function markRead(Request $request): RedirectResponse
    {
        $userId = $this->userId($request);

        $data = $request->validate([
            'id' => ['nullable', 'integer'],
        ]);

        app(MarkUserNotificationsReadAction::class)->execute($userId, $data['id'] ?? null);

        return redirect()->route('portal.notifications');
    }

    public function savePreferences(Request $request): RedirectResponse
    {
        $userId = $this->userId($request);

        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['boolean'],
        ]);

        app(SaveNotificationPreferencesAction::class)->execute($userId, $data['preferences']);

        return redirect()
            ->route('portal.notifications')
            ->with('success', 'Notification preferences saved.');
    }

    private function userId(Request $request): int
    {
        abort_unless($request->user() !== null, 403);

        return (int) $request->user()->id;
    }
}
