<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Actions\LinkAccountAction;
use App\Domains\Identity\Actions\ListLinkedAccountsAction;
use App\Domains\Identity\Actions\SwitchAccountAction;
use App\Domains\Identity\Actions\TwoFactorAction;
use App\Domains\Identity\Actions\UnlinkAccountAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * E7's switcher. Thin (rule 5) — every rule about who may link or switch lives
 * in the Actions, because this is identity.
 */
class LinkedAccountController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Identity/LinkedAccounts', [
            'accounts' => app(ListLinkedAccountsAction::class)->execute((int) $request->user()->id),
            'me' => ['name' => $request->user()->name],
            // STATUS §5lk: the way to two-step sign-in, from the page your name opens.
            'two_factor' => app(TwoFactorAction::class)->status($request->user()) + ['label' => __('security.link'), 'on' => __('security.state_on'), 'off' => __('security.state_off')],
            't' => Phrases::once('account'),
        ]);
    }

    public function store(Request $request, LinkAccountAction $link): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string'],
        ]);

        $target = $link->execute($request->user(), $data['identifier'], $data['password'], $request->ip());

        return back()->with('success', __('account.flash_linked', ['name' => $target->name]));
    }

    public function destroy(Request $request, int $account, UnlinkAccountAction $unlink): RedirectResponse
    {
        $unlink->execute($request->user(), $account, $request->ip());

        return back()->with('success', __('account.flash_unlinked'));
    }

    public function switch(Request $request, int $account, SwitchAccountAction $switch): RedirectResponse
    {
        $target = $switch->execute($request, $request->user(), $account);

        // Straight to /dashboard so the landing rules decide where the *other*
        // identity belongs, rather than this controller guessing.
        return redirect()->route('dashboard')
            ->with('success', __('account.flash_switched', ['name' => $target->name]));
    }
}
