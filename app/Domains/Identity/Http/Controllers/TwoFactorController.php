<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Actions\TwoFactorAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * STATUS §5lk: a person's own two-step sign-in — turn it on with an
 * authenticator app, see the recovery codes once, turn it off. Thin: the
 * rules are the Action's; the account is always the signed-in one.
 */
class TwoFactorController extends Controller
{
    public function show(Request $request, TwoFactorAction $twoFactor): Response
    {
        $user = $request->user();

        return Inertia::render('Identity/TwoFactor', [
            't' => Phrases::once('security'),
            'status' => $twoFactor->status($user),
            'pending' => $twoFactor->pendingFor($user),
            'recovery_codes' => $request->session()->get('two_factor_codes'),
        ]);
    }

    public function start(Request $request, TwoFactorAction $twoFactor): RedirectResponse
    {
        $twoFactor->start($request->user());

        return back();
    }

    public function confirm(Request $request, TwoFactorAction $twoFactor): RedirectResponse
    {
        $data = $request->validate(['code' => 'required|string|max:20']);
        $codes = $twoFactor->confirm($request->user(), $data['code']);

        return back()->with('success', __('security.on_flash'))->with('two_factor_codes', $codes);
    }

    public function recoveryCodes(Request $request, TwoFactorAction $twoFactor): RedirectResponse
    {
        $data = $request->validate(['password' => 'required|string']);
        $codes = $twoFactor->regenerateRecoveryCodes($request->user(), $data['password']);

        return back()->with('success', __('security.codes_flash'))->with('two_factor_codes', $codes);
    }

    public function disable(Request $request, TwoFactorAction $twoFactor): RedirectResponse
    {
        $data = $request->validate(['password' => 'required|string']);
        $twoFactor->disable($request->user(), $data['password']);

        return back()->with('success', __('security.off_flash'));
    }
}
