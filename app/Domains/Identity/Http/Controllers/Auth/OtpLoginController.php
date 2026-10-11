<?php

namespace App\Domains\Identity\Http\Controllers\Auth;

use App\Domains\Identity\Models\UserContact;
use App\Domains\Identity\Services\ContactNormalizer;
use App\Domains\Identity\Services\OtpService;
use App\Http\Controllers\Controller;
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
        $type = str_contains($identifier, '@') ? 'email' : 'mobile';
        $value = $type === 'email'
            ? $this->normalizer->normalizeEmail($identifier)
            : $this->normalizer->normalizePhone($identifier);

        $contact = UserContact::where('type', $type)
            ->where('value', $value)
            ->whereNotNull('verified_at')
            ->with('user')
            ->first();

        if (! $contact || ! $contact->user) {
            return back()->withErrors(['identifier' => __('signin.error_no_account')])->withInput();
        }

        $user = $contact->user;

        if (! $user->is_active) {
            return back()->withErrors(['identifier' => __('signin.error_inactive')])->withInput();
        }

        // Only admin and super_admin may use OTP login
        if (! $user->hasAnyRole(['admin', 'super_admin'])) {
            return back()->withErrors(['identifier' => __('signin.error_admin_only')])->withInput();
        }

        try {
            $this->otpService->send($contact, 'login');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        session([
            'otp_login_contact_id' => $contact->id,
            'otp_login_identifier' => $type === 'mobile' ? $identifier : $value,
        ]);

        return redirect()->route('otp.verify.form')
            ->with('success', __($type === 'mobile' ? 'signin.flash_code_sent_phone' : 'signin.flash_code_sent_email'));
    }

    /**
     * Show OTP verification form
     */
    public function showVerifyForm()
    {
        if (! session('otp_login_contact_id')) {
            return redirect()->route('otp.login.form')
                ->withErrors(['identifier' => __('signin.error_identifier_first')]);
        }

        // C16 slice N3: the screen counts the resend wait down and holds the
        // button until it ends, instead of a static "try again in N minutes".
        $contact = UserContact::find(session('otp_login_contact_id'));

        return view('auth.otp-verify', [
            'retryAfter' => $contact ? $this->otpService->retryAfterSeconds($contact, 'login') : 0,
        ]);
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
                ->withErrors(['identifier' => __('signin.error_session_expired')]);
        }

        $contact = UserContact::with('user')->find($contactId);

        if (! $contact || ! $contact->user) {
            return redirect()->route('otp.login.form')
                ->withErrors(['identifier' => __('signin.error_account_not_found')]);
        }

        try {
            $this->otpService->verify($contact, 'login', $request->code);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $user = $contact->user;

        // STATUS §5lk: an OTP is one step; with two-step sign-in on, the app's code is the other.
        if ($user->hasTwoFactor()) {
            session()->forget(['otp_login_contact_id', 'otp_login_identifier']);

            return TwoFactorChallengeController::begin($request, $user, true);
        }

        Auth::login($user, true);
        $user->forceFill(['last_login_at' => now()])->save();

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
                ->withErrors(['identifier' => __('signin.error_session_expired')]);
        }

        $contact = UserContact::find($contactId);

        if (! $contact) {
            return redirect()->route('otp.login.form')
                ->withErrors(['identifier' => __('signin.error_account_not_found')]);
        }

        try {
            $this->otpService->send($contact, 'login');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', __('signin.flash_new_code'));
    }
}
