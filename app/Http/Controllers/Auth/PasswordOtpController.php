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
        $identifier = trim($request->input('identifier'));

        // ── National ID / Passport → child or adult account lookup ───────────
        if (! str_contains($identifier, '@') && ! preg_match('/^\+?[\d\s\-]+$/', $identifier)) {
            $targetUser = \App\Models\User::whereRaw('LOWER(national_id) = ?', [strtolower($identifier)])->first()
                       ?? \App\Models\User::whereRaw('LOWER(passport) = ?', [strtolower($identifier)])->first();

            if ($targetUser) {
                // Check if this user is a child (linked via registration_students guardian)
                $studentProfile = \App\Models\RegistrationStudent::where('user_id', $targetUser->id)->first();
                $isChild = false;
                $resetContact = null;

                if ($studentProfile) {
                    // Try to find the primary guardian's mobile contact
                    $guardian = $studentProfile->guardians()->first();
                    if ($guardian) {
                        $resetContact = $guardian->contacts()
                            ->where('type', 'mobile')
                            ->whereNotNull('verified_at')
                            ->first();
                        $isChild = (bool) $resetContact;
                    }
                }

                // Fall back to child's own contacts if no guardian contact found
                if (! $resetContact) {
                    $resetContact = $targetUser->contacts()
                        ->whereNotNull('verified_at')
                        ->orderByDesc('is_primary')
                        ->first();
                }

                if ($resetContact) {
                    try {
                        $this->otpService->send($resetContact, 'password_reset');
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        return back()->withErrors(['identifier' => $e->errors()['contact'][0] ?? 'Too many requests.'])->withInput();
                    }
                }

                // Store both the contact for OTP and the actual user whose password to reset
                session([
                    'password_reset_identifier' => $identifier,
                    'password_reset_contact_id' => $resetContact?->id,
                    'password_reset_user_id'    => $targetUser->id,
                ]);

                $msg = $isChild
                    ? 'A verification code has been sent to your parent\'s registered mobile.'
                    : 'If an account exists, we have sent a verification code.';

                return redirect()->route('password.reset.verify')->with('status', $msg);
            }

            // No user found — silent (security best practice)
            session(['password_reset_identifier' => $identifier, 'password_reset_contact_id' => null, 'password_reset_user_id' => null]);
            return redirect()->route('password.reset.verify')
                ->with('status', 'If an account exists, we have sent a verification code.');
        }

        // ── Email or Mobile lookup via user_contacts ──────────────────────────
        $type  = str_contains($identifier, '@') ? 'email' : 'mobile';
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

        session([
            'password_reset_identifier' => $identifier,
            'password_reset_contact_id' => $contact?->id,
            'password_reset_user_id'    => null, // will be resolved from contact
        ]);
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
        $verified  = session('password_reset_verified');
        $userId    = session('password_reset_user_id');

        if (! $verified) {
            return redirect()->route('password.request')->withErrors(['identifier' => 'Session expired.']);
        }

        // Resolve which user's password to reset
        if ($userId) {
            // Child account: reset the child's password directly
            $user = \App\Models\User::find($userId);
        } elseif ($contactId) {
            // Normal account: resolve via contact
            $contact = UserContact::find($contactId);
            $user = $contact?->user;
        } else {
            $user = null;
        }

        if (! $user) {
            return redirect()->route('password.request')->withErrors(['identifier' => 'Account not found.']);
        }

        $user->update(['password' => Hash::make($request->input('password'))]);

        session()->forget(['password_reset_contact_id', 'password_reset_verified', 'password_reset_identifier', 'password_reset_user_id']);

        return redirect()->route('login')->with('status', 'Password reset successfully. You can now log in.');
    }
}
