<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * Supports three identifier types:
     *  - Email      → contains '@'          → looked up in user_contacts
     *  - Mobile     → digits only            → looked up in user_contacts (E.164 normalised)
     *  - National ID → anything else         → looked up directly on users.national_id
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identifier = trim($this->input('identifier'));
        $password   = $this->input('password');
        $normalizer = app(\App\Services\ContactNormalizer::class);

        $user = null;

        if (str_contains($identifier, '@')) {
            // ── Email login via user_contacts ──────────────────────────────
            $value   = $normalizer->normalizeEmail($identifier);
            $contact = \App\Models\UserContact::where('type', 'email')
                ->where('value', $value)
                ->whereNotNull('verified_at')
                ->first();
            $user = $contact?->user;

        } elseif (preg_match('/^\+?[\d\s\-]+$/', $identifier)) {
            // ── Mobile login via user_contacts ─────────────────────────────
            $value   = $normalizer->normalizePhone($identifier);
            $contact = \App\Models\UserContact::where('type', 'mobile')
                ->where('value', $value)
                ->whereNotNull('verified_at')
                ->first();
            $user = $contact?->user;

        } else {
            // ── National ID login directly on users table ──────────────────
            $user = \App\Models\User::whereRaw('LOWER(national_id) = ?', [strtolower($identifier)])->first();
        }

        if (! $user || ! \Illuminate\Support\Facades\Hash::check($password, $user->password)) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages(['identifier' => trans('auth.failed')]);
        }

        if (! $user->is_active) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages(['identifier' => 'Your account is inactive. Please contact support.']);
        }

        $user->update(['last_login_at' => now()]);
        Auth::login($user, $this->boolean('remember'));
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identifier' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('identifier')).'|'.$this->ip());
    }
}
