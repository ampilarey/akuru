<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyResetOtpRequest;
use App\Models\UserContact;
use App\Services\AccountResolverService;
use App\Services\ContactNormalizer;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordOtpController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
        protected ContactNormalizer $normalizer
    ) {
        $this->middleware('guest');
    }

    public function showRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendOtp(ForgotPasswordOtpRequest $request): RedirectResponse
    {
        $identifier = $request->input('identifier');
        $type = str_contains($identifier, '@') ? 'email' : 'mobile';
        $value = $type === 'email'
            ? $this->normalizer->normalizeEmail($identifier)
            : $this->normalizer->normalizePhone($identifier);

        $contact = UserContact::where('type', $type)->where('value', $value)->whereNotNull('verified_at')->first();

        if ($contact) {
            try {
                $this->otpService->send($contact, 'password_reset');
            } catch (\Illuminate\Validation\ValidationException $e) {
                return back()->withErrors(['identifier' => $e->errors()['contact'][0] ?? 'Too many requests.'])->withInput();
            }
        }

        session(['password_reset_identifier' => $identifier, 'password_reset_contact_id' => $contact?->id]);
        return redirect()->route('password.reset.verify')
            ->with('status', 'If an account exists with that contact, we have sent a verification code.');
    }

    public function showVerifyForm(): View
    {
        return view('auth.verify-reset-otp');
    }

    public function verifyOtp(VerifyResetOtpRequest $request): RedirectResponse
    {
        $contactId = session('password_reset_contact_id');
        if (!$contactId) {
            return back()->withErrors(['code' => 'Invalid or expired verification code.']);
        }

        $contact = UserContact::find($contactId);
        if (!$contact) {
            return back()->withErrors(['code' => 'Invalid or expired verification code.']);
        }

        try {
            $this->otpService->verify($contact, 'password_reset', $request->input('code'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors(['code' => $e->errors()['code'][0] ?? 'Invalid verification code.']);
        }

        session(['password_reset_verified' => true]);
        return redirect()->route('password.reset');
    }

    public function showResetForm(): View|RedirectResponse
    {
        if (!session('password_reset_verified') || !session('password_reset_contact_id')) {
            return redirect()->route('password.request');
        }
        return view('auth.reset-password');
    }

    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        $contactId = session('password_reset_contact_id');
        $verified = session('password_reset_verified');

        if (!$contactId || !$verified) {
            return redirect()->route('password.request')->withErrors(['identifier' => 'Session expired.']);
        }

        $contact = UserContact::findOrFail($contactId);
        $contact->user->update(['password' => Hash::make($request->input('password'))]);

        session()->forget(['password_reset_contact_id', 'password_reset_verified', 'password_reset_identifier']);

        return redirect()->route('login')->with('status', 'Password reset successfully. You can now log in.');
    }
}
