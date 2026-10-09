<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\Website\Actions\ConfirmEventRegistrationAction;
use App\Domains\Website\Actions\GetEventRegistrationAction;
use App\Domains\Website\Actions\ListPortalEventBoardAction;
use App\Domains\Website\Actions\RegisterForEventAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use App\Support\PersonName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalEventController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);

        $children = app(ListGuardianChildrenAction::class)->executeForGuardianUserId((int) $request->user()->id);
        $board = app(ListPortalEventBoardAction::class)->execute($children->pluck('id')->all());

        return Inertia::render('Portal/Events', [
            'children' => $children->map(fn ($child) => [
                'id' => $child->id,
                'name' => PersonName::ofStudent($child),
            ])->values(),
            'events' => $board['events'],
            'registrations' => $board['registrations'],
            't' => Phrases::once('portal'),
        ]);
    }

    public function register(Request $request, int $event): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
        ]);

        $childIds = app(ListGuardianChildrenAction::class)
            ->executeForGuardianUserId((int) $request->user()->id)
            ->pluck('id')
            ->all();

        abort_unless(in_array((int) $data['student_id'], $childIds, true), 403);

        app(RegisterForEventAction::class)->execute([
            'event_id' => $event,
            'student_id' => (int) $data['student_id'],
            'parent_user_id' => (int) $request->user()->id,
            'registration_source' => 'portal',
            'fallback_email' => $request->user()->email,
        ]);

        return redirect()->route('portal.events')->with('success', __('portal.flash_event_registered'));
    }

    public function confirm(Request $request, int $registration): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);

        $childIds = app(ListGuardianChildrenAction::class)
            ->executeForGuardianUserId((int) $request->user()->id)
            ->pluck('id')
            ->all();

        $row = app(GetEventRegistrationAction::class)->execute($registration);
        abort_unless($row !== null && in_array((int) $row['student_id'], $childIds, true), 403);

        app(ConfirmEventRegistrationAction::class)->execute($registration);

        return redirect()->route('portal.events')->with('success', __('portal.flash_event_confirmed'));
    }
}
