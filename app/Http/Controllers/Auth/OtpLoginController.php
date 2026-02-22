<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserContact;
use App\Services\ContactNormalizer;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class OtpLoginController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
        protected ContactNormalizer $normalizer
    ) {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Show admin OTP login form
     */
    public function showLoginForm()
    {
        return view('auth.otp-login');
    }

    /**
     * Request OTP — admin/super_admin only
     */
    public function requestOtp(Request $request)
    {
        $request->validate([
            'identifier' => ['required', 'string'],
        ]);

        $identifier = trim($request->identifier);
        $type  = str_contains($identifier, '@') ? 'email' : 'mobile';
        $value = $type === 'email'
            ? $this->normalizer->normalizeEmail($identifier)
            : $this->normalizer->normalizePhone($identifier);

        $contact = UserContact::where('type', $type)
            ->where('value', $value)
            ->whereNotNull('verified_at')
            ->with('user')
            ->first();

        if (! $contact || ! $contact->user) {
            return back()->withErrors(['identifier' => 'No account found with this email or phone number.'])->withInput();
        }

        $user = $contact->user;

        if (! $user->is_active) {
            return back()->withErrors(['identifier' => 'Your account is inactive. Please contact support.'])->withInput();
        }

        // Only admin and super_admin may use OTP login
        if (! $user->hasAnyRole(['admin', 'super_admin'])) {
            return back()->withErrors(['identifier' => 'OTP login is only available for admin accounts. Please use the password login.'])->withInput();
        }

        try {
            $this->otpService->send($contact, 'login');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        session([
            'otp_login_contact_id'   => $contact->id,
            'otp_login_identifier'   => $type === 'mobile' ? $identifier : $value,
        ]);

        return redirect()->route('otp.verify.form')
            ->with('success', 'OTP sent to your ' . ($type === 'mobile' ? 'phone' : 'email') . '. Please enter the 6-digit code.');
    }

    /**
     * Show OTP verification form
     */
    public function showVerifyForm()
    {
        if (! session('otp_login_contact_id')) {
            return redirect()->route('otp.login.form')
                ->withErrors(['identifier' => 'Please enter your email or phone number first.']);
        }

        return view('auth.otp-verify');
    }

    /**
     * Verify OTP and log in
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $contactId = session('otp_login_contact_id');

        if (! $contactId) {
            return redirect()->route('otp.login.form')
                ->withErrors(['identifier' => 'Session expired. Please try again.']);
        }

        $contact = UserContact::with('user')->find($contactId);

        if (! $contact || ! $contact->user) {
            return redirect()->route('otp.login.form')
                ->withErrors(['identifier' => 'Account not found. Please try again.']);
        }

        try {
            $this->otpService->verify($contact, 'login', $request->code);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $user = $contact->user;

        Auth::login($user, true);
        $user->update(['last_login_at' => now()]);

        session()->forget(['otp_login_contact_id', 'otp_login_identifier']);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request)
    {
        $contactId = session('otp_login_contact_id');

        if (! $contactId) {
            return redirect()->route('otp.login.form')
                ->withErrors(['identifier' => 'Session expired. Please try again.']);
        }

        $contact = UserContact::find($contactId);

        if (! $contact) {
            return redirect()->route('otp.login.form')
                ->withErrors(['identifier' => 'Account not found.']);
        }

        try {
            $this->otpService->send($contact, 'login');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'New OTP sent.');
    }
}
