<?php

namespace App\Domains\Identity\Http\Controllers\Auth;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Identity\Services\ContactNormalizer;
use App\Domains\Identity\Services\OtpService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyResetOtpRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordOtpController extends Controller
{
    /**
     * The reset in progress, one record: the contact the code went to, the
     * account it resets (a child's, when the code went to a parent) and the
     * contact whose code was proved. Asking again replaces the whole record,
     * so a code proved for one contact never carries over to another; and the
     * other reset pages keep their own record (STATUS §5rc). Both used to share
     * loose keys, and a code proved for one's own number reset any account.
     */
    private const STATE = 'password_reset';

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
            // The passport lookup that used to sit here queried
            // `users.passport`, and **that column does not exist** — passport
            // lives on `students`, `registration_students` and `staff_profiles`.
            // Because PHP only evaluates the right-hand side of `??` when the
            // left is null, every identifier that was *not* a matching national
            // ID reached it and returned a 500 from a public, unauthenticated
            // endpoint. A wrong guess crashed "forgot password".
            //
            // It is removed rather than repointed: on those three tables
            // `passport` is cast `encrypted`, so `LOWER(passport) = ?` could
            // never match a value anyway. Making passport reset actually work
            // needs a blind index or a decrypt-and-scan, which is a design
            // decision rather than a repair.
            $targetUser = User::whereRaw('LOWER(national_id) = ?', [strtolower($identifier)])->first();

            if ($targetUser) {
                $studentProfile = $targetUser->student;
                $isChild = false;
                $resetContact = null;

                if ($studentProfile) {
                    $guardianUser = $studentProfile->guardians()->first()?->user;
                    if ($guardianUser) {
                        $resetContact = $guardianUser->contacts()
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
                    } catch (ValidationException $e) {
                        return back()->withErrors(['identifier' => $e->errors()['contact'][0] ?? 'Too many requests.'])->withInput();
                    }
                }

                // Store both the contact for OTP and the actual user whose password to reset
                session([self::STATE => ['contact_id' => $resetContact?->id, 'user_id' => $targetUser->id, 'verified_contact_id' => null]]);

                $msg = $isChild
                    ? 'A verification code has been sent to your parent\'s registered mobile.'
                    : 'If an account exists, we have sent a verification code.';

                return redirect()->route('password.reset.verify')->with('status', $msg);
            }

            // No user found — silent (security best practice)
            session([self::STATE => ['contact_id' => null, 'user_id' => null, 'verified_contact_id' => null]]);

            return redirect()->route('password.reset.verify')
                ->with('status', 'If an account exists, we have sent a verification code.');
        }

        // ── Email or Mobile lookup via user_contacts ──────────────────────────
        $type = str_contains($identifier, '@') ? 'email' : 'mobile';
        $value = $type === 'email'
            ? $this->normalizer->normalizeEmail($identifier)
            : $this->normalizer->normalizePhone($identifier);

        $contact = UserContact::where('type', $type)->where('value', $value)->whereNotNull('verified_at')->first();

        if ($contact) {
            try {
                $this->otpService->send($contact, 'password_reset');
            } catch (ValidationException $e) {
                return back()->withErrors(['identifier' => $e->errors()['contact'][0] ?? 'Too many requests.'])->withInput();
            }
        }

        // The account is resolved from the contact.
        session([self::STATE => ['contact_id' => $contact?->id, 'user_id' => null, 'verified_contact_id' => null]]);

        return redirect()->route('password.reset.verify')
            ->with('status', 'If an account exists with that contact, we have sent a verification code.');
    }

    public function showVerifyForm(): View
    {
        return view('auth.verify-reset-otp');
    }

    public function verifyOtp(VerifyResetOtpRequest $request): RedirectResponse
    {
        $contactId = session(self::STATE.'.contact_id');
        $contact = $contactId ? UserContact::find($contactId) : null;
        if (! $contact) {
            return back()->withErrors(['code' => 'Invalid or expired verification code.']);
        }

        try {
            $this->otpService->verify($contact, 'password_reset', $request->input('code'));
        } catch (ValidationException $e) {
            return back()->withErrors(['code' => $e->errors()['code'][0] ?? 'Invalid verification code.']);
        }

        // The proof belongs to this contact, and is read back against it.
        session()->put(self::STATE.'.verified_contact_id', $contact->id);

        return redirect()->route('password.reset');
    }

    public function showResetForm(): View|RedirectResponse
    {
        if (! $this->provedState()) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password');
    }

    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        $state = $this->provedState();

        if (! $state) {
            return redirect()->route('password.request')->withErrors(['identifier' => 'Session expired.']);
        }

        // Resolve which user's password to reset: a child's directly, anyone
        // else's through the contact whose code was proved.
        $user = $state['user_id'] ? User::find($state['user_id']) : UserContact::find($state['contact_id'])?->user;

        if (! $user) {
            return redirect()->route('password.request')->withErrors(['identifier' => 'Account not found.']);
        }

        $user->update(['password' => Hash::make($request->input('password'))]);

        session()->forget(self::STATE);

        return redirect()->route('login')->with('status', 'Password reset successfully. You can now log in.');
    }

    /**
     * The reset in progress, only once a code was proved for the contact it
     * names.
     *
     * @return array{contact_id: int, user_id: ?int, verified_contact_id: int}|null
     */
    private function provedState(): ?array
    {
        $state = session(self::STATE);

        return is_array($state) && ! empty($state['contact_id']) && ($state['verified_contact_id'] ?? null) === $state['contact_id']
            ? $state
            : null;
    }
}
