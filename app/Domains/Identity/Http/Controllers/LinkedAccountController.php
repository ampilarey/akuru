<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Actions\LinkAccountAction;
use App\Domains\Identity\Actions\ListLinkedAccountsAction;
use App\Domains\Identity\Actions\SwitchAccountAction;
use App\Domains\Identity\Actions\UnlinkAccountAction;
use App\Http\Controllers\Controller;
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
        ]);
    }

    public function store(Request $request, LinkAccountAction $link): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string'],
        ]);

        $target = $link->execute($request->user(), $data['identifier'], $data['password'], $request->ip());

        return back()->with('success', $target->name.' is linked. You can switch to it from any screen.');
    }

    public function destroy(Request $request, int $account, UnlinkAccountAction $unlink): RedirectResponse
    {
        $unlink->execute($request->user(), $account, $request->ip());

        return back()->with('success', 'Unlinked. Both accounts have lost the shortcut.');
    }

    public function switch(Request $request, int $account, SwitchAccountAction $switch): RedirectResponse
    {
        $target = $switch->execute($request, $request->user(), $account);

        // Straight to /dashboard so the landing rules decide where the *other*
        // identity belongs, rather than this controller guessing.
        return redirect()->route('dashboard')
            ->with('success', 'You are now signed in as '.$target->name.'.');
    }
}
