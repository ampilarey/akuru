<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Academics\Actions\AdvancePickupNoticeAction;
use App\Domains\Academics\Actions\ListPickupNoticesForGuardianAction;
use App\Domains\Academics\Actions\OpenPickupWindowAction;
use App\Domains\Academics\Actions\RequestPickupAction;
use App\Domains\People\Actions\GuardianHasPickupPinAction;
use App\Domains\People\Actions\ListCollectableChildrenAction;
use App\Domains\People\Actions\SetPickupPinAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The family side of E8.
 *
 * Thin (rule 5). Every safety gate — the window, the collect permission, the
 * PIN — lives in `RequestPickupAction`, so there is exactly one place to read
 * when somebody asks "what stops the wrong adult taking a child?".
 */
class PortalPickupController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->id;

        return Inertia::render('Portal/Pickup', [
            'is_open' => app(OpenPickupWindowAction::class)->isOpen(),
            'has_pin' => app(GuardianHasPickupPinAction::class)->execute($userId),
            'children' => app(ListCollectableChildrenAction::class)->execute($userId),
            'notices' => app(ListPickupNoticesForGuardianAction::class)->execute($userId),
        ]);
    }

    public function setPin(Request $request, SetPickupPinAction $set): RedirectResponse
    {
        $data = $request->validate(['pin' => ['required', 'string', 'max:8']]);

        $set->execute((int) $request->user()->id, $data['pin']);

        return back()->with('success', 'Your pick-up PIN is saved.');
    }

    public function request(Request $request, RequestPickupAction $requestPickup): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'min:1'],
            'pin' => ['required', 'string', 'max:8'],
            'note' => ['nullable', 'string', 'max:191'],
        ]);

        $requestPickup->execute(
            (int) $request->user()->id,
            (int) $data['student_id'],
            $data['pin'],
            $data['note'] ?? null,
        );

        return back()->with('success', 'The school has been told. Please come to reception.');
    }

    public function confirm(Request $request, int $notice, AdvancePickupNoticeAction $advance): RedirectResponse
    {
        $advance->collectById($notice, (int) $request->user()->id);

        return back()->with('success', 'Thank you — the loop is closed.');
    }
}
