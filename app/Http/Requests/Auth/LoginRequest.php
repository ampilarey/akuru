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
     * The three identifier types and their asymmetries now live in
     * `ResolveUserByIdentifierAction`. What stays here is everything that is
     * specific to *logging in*: rate limiting, the password check, the active
     * flag and the session.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identifier = trim($this->input('identifier'));
        $password = $this->input('password');

        // Resolution lives in an Action so that E7's account linking can prove
        // you own a second account without signing you into it (rule 11).
        $user = app(\App\Domains\Identity\Actions\ResolveUserByIdentifierAction::class)
            ->execute($identifier);

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
