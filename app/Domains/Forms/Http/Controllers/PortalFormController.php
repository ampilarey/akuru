<?php

namespace App\Domains\Forms\Http\Controllers;

use App\Domains\Forms\Actions\ConfirmFormResponseAction;
use App\Domains\Forms\Actions\ListFormsForUserAction;
use App\Domains\Forms\Actions\ListPendingConfirmationsAction;
use App\Domains\Forms\Actions\SubmitFormResponseAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalFormController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render('Portal/Forms', [
            'forms' => app(ListFormsForUserAction::class)
                ->execute((int) $user->id, $user->getRoleNames()->all())
                ->all(),
            // E6b: a guardian's queue. Without it, "unconfirmed" is a silent
            // state nobody acts on.
            'pending' => app(ListPendingConfirmationsAction::class)->execute((int) $user->id)->all(),
        ]);
    }

    public function confirm(Request $request, int $response): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        // Every rule — guardianship, the pupil-cannot-confirm-their-own case,
        // the anonymous case — is enforced inside the action.
        app(ConfirmFormResponseAction::class)->execute($response, (int) $user->id);

        return redirect()->route('portal.forms')->with('success', 'Confirmed.');
    }

    public function submit(Request $request, int $form): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $data = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        // Audience and open/closed are both enforced inside the action, with
        // the same matcher the listing uses.
        app(SubmitFormResponseAction::class)->execute(
            $form,
            (int) $user->id,
            $data['answers'],
            $user->getRoleNames()->all(),
        );

        return redirect()->route('portal.forms')->with('success', 'Answer submitted.');
    }
}
