<?php

namespace App\Domains\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function setPasswordForm(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        // The form has to know whether to ask for the current password, or a
        // user who has one would be shown a screen they cannot complete: the
        // validation below would demand a field the view never rendered.
        return view('account.set-password', [
            'needsCurrentPassword' => ! $user->force_password_change,
        ]);
    }

    public function setPassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        // SPEC §44/§45, the lesson this repo has already learned once: *"do not
        // rely only on frontend button hiding"* / *"backend must enforce
        // permissions"*.
        //
        // The dashboard only offers this screen to an account that has no
        // usable password. The **route** offered it to everybody, and skipped
        // the `current_password` check that `ProfileController` requires on the
        // other password route — so one door was locked and the other was not.
        // Session access (a stolen cookie, an unlocked shared device, an XSS
        // anywhere) became permanent account takeover, with the real owner
        // locked out and the attacker never having known the password.
        //
        // `force_password_change` is the genuine "no usable password" state:
        // `AccountResolverService` creates OTP-only accounts with a random
        // 40-character hash nobody has, and sets this flag. Those accounts
        // cannot supply a current password and must not be asked for one.
        // Everybody else must.
        $rules = [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];

        if (! $user->force_password_change) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $request->validate($rules);

        $user->update([
            'password' => Hash::make($request->input('password')),
            'force_password_change' => false,
        ]);

        return redirect()->intended(route('public.home'))
            ->with('success', 'Password set successfully. You can now log in with your mobile number and password.');
    }
}
