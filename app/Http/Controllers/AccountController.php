<?php

namespace App\Http\Controllers;

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
        return view('account.set-password');
    }

    public function setPassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $request->validate([
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $user->update([
            'password'              => Hash::make($request->input('password')),
            'force_password_change' => false,
        ]);

        return redirect()->intended(route('public.home'))
            ->with('success', 'Password set successfully. You can now log in with your mobile number and password.');
    }
}
