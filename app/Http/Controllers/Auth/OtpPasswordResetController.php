<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserContact;
use App\Services\ContactNormalizer;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OtpPasswordResetController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
        protected ContactNormalizer $normalizer,
    ) {
        $this->middleware('guest');
    }

    /** Step 1 – show the "enter mobile/email" form */
    public function showRequestForm()
    {
        return view('auth.passwords.otp-request');
    }

    /** Step 1 – send OTP */
    public function requestOtp(Request $request)
    {
        $request->validate([
            'contact' => ['required', 'string', 'max:120'],
        ]);

        $raw     = trim($request->input('contact'));
        $type    = str_contains($raw, '@') ? 'email' : 'mobile';
        $normalized = $this->normalizer->normalize($type, $raw);

        // Intentionally do NOT reveal whether the account exists.
        $contact = UserContact::where('type', $type)
            ->where('value', $normalized)
            ->first();

        if ($contact) {
            try {
                $this->otpService->send($contact, 'password_reset');
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }
        }

        // Generic message always shown
        session([
            'password_reset_contact_type'  => $type,
            'password_reset_contact_value' => $normalized,
            'password_reset_contact_id'    => $contact?->id,
        ]);

        return redirect()->route('password.otp.verify.form')
            ->with('success', "If that contact is registered, a verification code has been sent.");
    }

    /** Step 2 – show OTP input form */
    public function showVerifyForm()
    {
        if (! session('password_reset_contact_value')) {
            return redirect()->route('password.otp.request');
        }

        return view('auth.passwords.otp-verify');
    }

    /** Step 2 – verify OTP */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $contactId = session('password_reset_contact_id');
        if (! $contactId) {
            return redirect()->route('password.otp.request')
                ->withErrors(['contact' => 'Session expired. Please try again.']);
        }

        $contact = UserContact::find($contactId);
        if (! $contact) {
            return redirect()->route('password.otp.request')
                ->withErrors(['contact' => 'Session expired. Please try again.']);
        }

        try {
            $this->otpService->verify($contact, 'password_reset', $request->input('code'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        session(['password_reset_otp_verified' => true]);

        return redirect()->route('password.otp.reset.form')
            ->with('success', 'Code verified. Please set your new password.');
    }

    /** Step 3 – show new-password form */
    public function showResetForm()
    {
        if (! session('password_reset_otp_verified') || ! session('password_reset_contact_id')) {
            return redirect()->route('password.otp.request');
        }

        return view('auth.passwords.otp-reset');
    }

    /** Step 3 – save new password */
    public function reset(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! session('password_reset_otp_verified') || ! session('password_reset_contact_id')) {
            return redirect()->route('password.otp.request')
                ->withErrors(['contact' => 'Session expired. Please try again.']);
        }

        $contact = UserContact::find(session('password_reset_contact_id'));
        $user    = $contact?->user;

        if (! $user) {
            session()->forget(['password_reset_otp_verified', 'password_reset_contact_id', 'password_reset_contact_value', 'password_reset_contact_type']);
            return redirect()->route('password.otp.request')
                ->withErrors(['contact' => 'Account not found.']);
        }

        $user->update(['password' => Hash::make($request->input('password'))]);

        session()->forget(['password_reset_otp_verified', 'password_reset_contact_id', 'password_reset_contact_value', 'password_reset_contact_type']);

        Log::info('Password reset via OTP', ['user_id' => $user->id]);

        return redirect()->route('login')
            ->with('status', 'Password reset successfully. You can now log in.');
    }

    /** Resend OTP */
    public function resendOtp(Request $request)
    {
        $contactId = session('password_reset_contact_id');

        if (! $contactId) {
            return redirect()->route('password.otp.request');
        }

        $contact = UserContact::find($contactId);
        if (! $contact) {
            return redirect()->route('password.otp.request');
        }

        try {
            $this->otpService->send($contact, 'password_reset');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'A new code has been sent.');
    }
}
