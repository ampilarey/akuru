<?php

namespace App\Domains\Identity\Http\Controllers\Auth;

use App\Domains\Identity\Models\UserContact;
use App\Domains\Identity\Services\ContactNormalizer;
use App\Domains\Identity\Services\OtpService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OtpPasswordResetController extends Controller
{
    /**
     * The reset in progress, one record: the contact typed, the contact found
     * for it and the contact whose code was proved. Asking again replaces the
     * whole record, so a code proved for one's own number never resets the
     * account of a number asked for after it; and the forgot-password pages
     * keep their own record (STATUS §5rc).
     */
    private const STATE = 'password_otp_reset';

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

        $raw = trim($request->input('contact'));
        $type = str_contains($raw, '@') ? 'email' : 'mobile';
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
        session([self::STATE => ['type' => $type, 'value' => $normalized, 'contact_id' => $contact?->id, 'verified_contact_id' => null]]);

        return redirect()->route('password.otp.verify.form')
            ->with('success', 'If that contact is registered, a verification code has been sent.');
    }

    /** Step 2 – show OTP input form */
    public function showVerifyForm()
    {
        if (! session(self::STATE.'.value')) {
            return redirect()->route('password.otp.request');
        }

        // C16 slice N3: the resend wait, counted down on the screen.
        $contact = $this->stateContact();

        return view('auth.passwords.otp-verify', [
            'contactValue' => session(self::STATE.'.value'),
            'retryAfter' => $contact ? $this->otpService->retryAfterSeconds($contact, 'password_reset') : 0,
        ]);
    }

    /** Step 2 – verify OTP */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $contact = $this->stateContact();
        if (! $contact) {
            return redirect()->route('password.otp.request')
                ->withErrors(['contact' => 'Session expired. Please try again.']);
        }

        try {
            $this->otpService->verify($contact, 'password_reset', $request->input('code'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        // The proof belongs to this contact, and is read back against it.
        session()->put(self::STATE.'.verified_contact_id', $contact->id);

        return redirect()->route('password.otp.reset.form')
            ->with('success', 'Code verified. Please set your new password.');
    }

    /** Step 3 – show new-password form */
    public function showResetForm()
    {
        if (! $this->provedContact()) {
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

        $contact = $this->provedContact();
        if (! $contact) {
            return redirect()->route('password.otp.request')
                ->withErrors(['contact' => 'Session expired. Please try again.']);
        }

        $user = $contact->user;

        if (! $user) {
            session()->forget(self::STATE);

            return redirect()->route('password.otp.request')
                ->withErrors(['contact' => 'Account not found.']);
        }

        $user->update(['password' => Hash::make($request->input('password'))]);

        session()->forget(self::STATE);

        Log::info('Password reset via OTP', ['user_id' => $user->id]);

        return redirect()->route('login')
            ->with('status', 'Password reset successfully. You can now log in.');
    }

    /** Resend OTP */
    public function resendOtp(Request $request)
    {
        $contact = $this->stateContact();
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

    /** The contact the reset in progress sent its code to. */
    private function stateContact(): ?UserContact
    {
        $contactId = session(self::STATE.'.contact_id');

        return $contactId ? UserContact::find($contactId) : null;
    }

    /** That contact, only once its code was proved. */
    private function provedContact(): ?UserContact
    {
        $contactId = session(self::STATE.'.contact_id');

        return $contactId && session(self::STATE.'.verified_contact_id') === $contactId ? UserContact::find($contactId) : null;
    }
}
