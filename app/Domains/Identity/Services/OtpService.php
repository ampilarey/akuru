<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\Otp;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Notifications\OtpEmailNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpService
{
    /*
     * SPEC §32's numbers, read from config rather than frozen here.
     *
     * The old constants were 5 sends per 60 minutes and a 30-second resend
     * cooldown. §32 says "Maximum 3 OTP sends per phone number per 15 minutes"
     * and "Minimum 60-second resend cooldown" — so the burst allowance was
     * nearly double, and the cooldown was HALF the mandated floor, which
     * doubles the achievable send rate. §32 explains why that matters in its
     * own words: "This protects future Dhiraagu SMS integration from cost abuse
     * and spam." Every send is a message somebody pays for.
     *
     * §32 also requires these be configurable, so an operator can tighten them
     * under attack without waiting for a deploy.
     */
    protected function maxSends(): int
    {
        return max(1, (int) config('otp.max_sends', 3));
    }

    protected function sendWindowMinutes(): int
    {
        return max(1, (int) config('otp.send_window_minutes', 15));
    }

    protected function resendCooldownSeconds(): int
    {
        return max(1, (int) config('otp.resend_cooldown_seconds', 60));
    }

    protected function maxVerifyAttempts(): int
    {
        return max(1, (int) config('otp.max_verify_attempts', 10));
    }

    protected function verifyWindowMinutes(): int
    {
        return max(1, (int) config('otp.verify_window_minutes', 15));
    }

    protected function maxAttemptsPerCode(): int
    {
        return max(1, (int) config('otp.max_attempts_per_code', 5));
    }

    public function __construct(
        protected SmsSenderInterface $smsGateway,
        protected ContactNormalizer $normalizer
    ) {}

    public function send(UserContact $contact, string $purpose): void
    {
        $this->validatePurpose($purpose);
        $sendKey = $this->sendRateLimitKey($contact, $purpose);
        $cooldownKey = $this->resendCooldownKey($contact, $purpose);

        // §32: max sends per contact per window.
        if (RateLimiter::tooManyAttempts($sendKey, $this->maxSends())) {
            $seconds = RateLimiter::availableIn($sendKey);
            $this->recordAbuse('send_rate', $contact, $purpose, $this->maxSends() + 1, $this->maxSends());
            throw ValidationException::withMessages([
                'contact' => ['Too many OTP requests. Please try again in '.ceil($seconds / 60).' minutes.'],
            ]);
        }

        // §32: minimum resend cooldown.
        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $seconds = RateLimiter::availableIn($cooldownKey);
            $this->recordAbuse('resend_cooldown', $contact, $purpose, 1, 1);
            throw ValidationException::withMessages([
                'contact' => ["Please wait {$seconds} seconds before requesting a new code."],
            ]);
        }

        $code = $this->generateCode();
        $otp = Otp::createForContact($contact, $purpose, $code);

        try {
            $this->dispatchCode($contact, $code, $purpose);
        } catch (\Throwable $e) {
            // Cleanup OTP record so it cannot be abused after a failed send
            $otp->delete();
            throw ValidationException::withMessages([
                'contact' => ['Unable to send verification code. Please try again.'],
            ]);
        }

        RateLimiter::hit($sendKey, $this->sendWindowMinutes() * 60);
        RateLimiter::hit($cooldownKey, $this->resendCooldownSeconds());
    }

    public function verify(UserContact $contact, string $purpose, string $code): void
    {
        $this->validatePurpose($purpose);
        $key = $this->verifyRateLimitKey($contact, $purpose);

        if (RateLimiter::tooManyAttempts($key, $this->maxVerifyAttempts())) {
            $seconds = RateLimiter::availableIn($key);
            $this->recordAbuse('verify_rate', $contact, $purpose, $this->maxVerifyAttempts() + 1, $this->maxVerifyAttempts());
            throw ValidationException::withMessages([
                'code' => ['Too many verification attempts. Please try again in '.ceil($seconds / 60).' minutes.'],
            ]);
        }

        $otp = Otp::where('user_contact_id', $contact->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otp) {
            RateLimiter::hit($key, $this->verifyWindowMinutes() * 60);
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired verification code.'],
            ]);
        }

        if ($otp->attempts >= $this->maxAttemptsPerCode()) {
            $this->recordAbuse('code_attempts', $contact, $purpose, (int) $otp->attempts, $this->maxAttemptsPerCode());
            throw ValidationException::withMessages([
                'code' => ['Too many failed attempts. Please request a new code.'],
            ]);
        }

        if (! $otp->verify($code)) {
            RateLimiter::hit($key, $this->verifyWindowMinutes() * 60);
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code.'],
            ]);
        }

        // Belt-and-suspenders: mark used immediately even though Otp::verify() already does it.
        if (! $otp->used_at) {
            $otp->update(['used_at' => now()]);
        }

        RateLimiter::clear($key);
    }

    /**
     * §32: "OTP abuse event logging for admin review." Recording must never be
     * the reason a request fails — the limit has already done its job by the
     * time this runs, and losing the log entry is better than turning a
     * throttle into a 500.
     */
    protected function recordAbuse(string $kind, UserContact $contact, string $purpose, int $observed, int $threshold): void
    {
        try {
            app(\App\Domains\Identity\Actions\RecordOtpAbuseEventAction::class)
                ->execute($kind, $contact, $purpose, $observed, $threshold);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Same contract as `recordAbuse()`, for the registration path where no
     * `UserContact` row exists yet.
     */
    protected function recordAbuseForValue(string $kind, string $value, string $channel, string $purpose, int $observed, int $threshold): void
    {
        try {
            app(\App\Domains\Identity\Actions\RecordOtpAbuseEventAction::class)
                ->forValue($kind, $value, $channel, $purpose, $observed, $threshold);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // -------------------------------------------------------------------------

    protected function dispatchCode(UserContact $contact, string $code, string $purpose): void
    {
        $minutes = $purpose === 'verify_contact' ? 5 : 15;

        if ($contact->type === 'mobile') {
            $phone = $contact->value;
            if (! str_starts_with($phone, '+')) {
                $phone = $this->normalizer->normalizePhone($phone);
            }
            $result = $this->smsGateway->sendOtp($phone, $code);
            if (! ($result['success'] ?? false)) {
                throw new \RuntimeException('SMS gateway failed');
            }
        } else {
            Notification::route('mail', $contact->value)
                ->notify(new OtpEmailNotification($code, $purpose, $minutes));
        }
    }

    protected function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    protected function sendRateLimitKey(UserContact $contact, string $purpose): string
    {
        return 'otp:send:'.$contact->id.':'.$purpose;
    }

    protected function resendCooldownKey(UserContact $contact, string $purpose): string
    {
        return 'otp:cooldown:'.$contact->id.':'.$purpose;
    }

    protected function verifyRateLimitKey(UserContact $contact, string $purpose): string
    {
        return 'otp:verify:'.$contact->id.':'.$purpose;
    }

    protected function validatePurpose(string $purpose): void
    {
        if (! in_array($purpose, ['verify_contact', 'password_reset', 'login', 'enroll'], true)) {
            throw new \InvalidArgumentException("Invalid OTP purpose: {$purpose}");
        }
    }

    // -------------------------------------------------------------------------
    // Cache-based OTP for new registrations (no UserContact / User in DB yet)
    // -------------------------------------------------------------------------

    /**
     * Send an OTP for a pending new-account registration.
     * Stores the hashed code in cache — no DB write happens here.
     */
    public function sendForNewRegistration(string $type, string $normalizedValue): void
    {
        $cacheKey = $this->newRegCacheKey($type, $normalizedValue);
        $sendKey = 'new_reg_otp_send:'.md5($type.$normalizedValue);
        $cooldownKey = 'new_reg_otp_cooldown:'.md5($type.$normalizedValue);

        if (RateLimiter::tooManyAttempts($sendKey, $this->maxSends())) {
            $seconds = RateLimiter::availableIn($sendKey);
            $this->recordAbuseForValue('send_rate', $normalizedValue, $type, 'verify_contact',
                $this->maxSends() + 1, $this->maxSends());
            throw ValidationException::withMessages([
                'contact_value' => ['Too many OTP requests. Please try again in '.ceil($seconds / 60).' minutes.'],
            ]);
        }
        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $seconds = RateLimiter::availableIn($cooldownKey);
            // Logged like the signed-in path's cooldown, and for a stronger
            // reason: this branch is reachable without an account, so it is the
            // cheapest place in the app to burn SMS credit.
            $this->recordAbuseForValue('resend_cooldown', $normalizedValue, $type, 'verify_contact', 1, 1);
            throw ValidationException::withMessages([
                'contact_value' => ["Please wait {$seconds} seconds before requesting a new code."],
            ]);
        }

        $code = $this->generateCode();
        \Illuminate\Support\Facades\Cache::put($cacheKey, [
            'hash' => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(10));

        try {
            if ($type === 'mobile') {
                $phone = str_starts_with($normalizedValue, '+')
                    ? $normalizedValue
                    : $this->normalizer->normalizePhone($normalizedValue);
                $result = $this->smsGateway->sendOtp($phone, $code);
                if (! ($result['success'] ?? false)) {
                    throw new \RuntimeException('SMS gateway failed');
                }
            } else {
                Notification::route('mail', $normalizedValue)
                    ->notify(new OtpEmailNotification($code, 'verify_contact', 10));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
            throw ValidationException::withMessages([
                'contact_value' => ['Unable to send verification code. Please try again.'],
            ]);
        }

        RateLimiter::hit($sendKey, $this->sendWindowMinutes() * 60);
        RateLimiter::hit($cooldownKey, $this->resendCooldownSeconds());
    }

    /**
     * Verify an OTP for a pending new-account registration.
     * Throws ValidationException on failure; clears cache on success.
     */
    public function verifyForNewRegistration(string $type, string $normalizedValue, string $code): void
    {
        $cacheKey = $this->newRegCacheKey($type, $normalizedValue);
        $verifyKey = 'new_reg_otp_verify:'.md5($type.$normalizedValue);

        if (RateLimiter::tooManyAttempts($verifyKey, $this->maxVerifyAttempts())) {
            $seconds = RateLimiter::availableIn($verifyKey);
            $this->recordAbuseForValue('verify_rate', $normalizedValue, $type, 'verify_contact',
                $this->maxVerifyAttempts() + 1, $this->maxVerifyAttempts());
            throw ValidationException::withMessages([
                'code' => ['Too many attempts. Please try again in '.ceil($seconds / 60).' minutes.'],
            ]);
        }

        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (! $cached) {
            throw ValidationException::withMessages([
                'code' => ['Verification code has expired. Please start again.'],
            ]);
        }

        if (($cached['attempts'] ?? 0) >= $this->maxAttemptsPerCode()) {
            $this->recordAbuseForValue('code_attempts', $normalizedValue, $type, 'verify_contact',
                (int) ($cached['attempts'] ?? 0), $this->maxAttemptsPerCode());
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
            throw ValidationException::withMessages([
                'code' => ['Too many failed attempts. Please start the registration again.'],
            ]);
        }

        if (! Hash::check($code, $cached['hash'])) {
            \Illuminate\Support\Facades\Cache::put($cacheKey, array_merge($cached, [
                'attempts' => ($cached['attempts'] ?? 0) + 1,
            ]), now()->addMinutes(10));
            RateLimiter::hit($verifyKey, $this->verifyWindowMinutes() * 60);
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code.'],
            ]);
        }

        \Illuminate\Support\Facades\Cache::forget($cacheKey);
        RateLimiter::clear($verifyKey);
    }

    private function newRegCacheKey(string $type, string $value): string
    {
        return 'new_reg_otp:'.$type.':'.md5($value);
    }
}
