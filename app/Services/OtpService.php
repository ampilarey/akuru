<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\UserContact;
use App\Notifications\OtpEmailNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpService
{
    protected const MAX_SEND_ATTEMPTS  = 5;
    protected const SEND_DECAY_MINUTES = 60;
    protected const RESEND_COOLDOWN_SECONDS = 30;
    protected const MAX_VERIFY_ATTEMPTS  = 10;
    protected const VERIFY_DECAY_MINUTES = 15;
    protected const OTP_MAX_ATTEMPTS = 5;

    public function __construct(
        protected SmsGatewayService $smsGateway,
        protected ContactNormalizer $normalizer
    ) {}

    public function send(UserContact $contact, string $purpose): void
    {
        $this->validatePurpose($purpose);
        $sendKey   = $this->sendRateLimitKey($contact, $purpose);
        $cooldownKey = $this->resendCooldownKey($contact, $purpose);

        // Hard per-contact throttle (5 sends / 60 min)
        if (RateLimiter::tooManyAttempts($sendKey, self::MAX_SEND_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($sendKey);
            throw ValidationException::withMessages([
                'contact' => ['Too many OTP requests. Please try again in ' . ceil($seconds / 60) . ' minutes.'],
            ]);
        }

        // Resend cooldown (30 s between sends)
        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $seconds = RateLimiter::availableIn($cooldownKey);
            throw ValidationException::withMessages([
                'contact' => ["Please wait {$seconds} seconds before requesting a new code."],
            ]);
        }

        $code = $this->generateCode();
        $otp  = Otp::createForContact($contact, $purpose, $code);

        try {
            $this->dispatchCode($contact, $code, $purpose);
        } catch (\Throwable $e) {
            // Cleanup OTP record so it cannot be abused after a failed send
            $otp->delete();
            throw ValidationException::withMessages([
                'contact' => ['Unable to send verification code. Please try again.'],
            ]);
        }

        RateLimiter::hit($sendKey,    self::SEND_DECAY_MINUTES * 60);
        RateLimiter::hit($cooldownKey, self::RESEND_COOLDOWN_SECONDS);
    }

    public function verify(UserContact $contact, string $purpose, string $code): void
    {
        $this->validatePurpose($purpose);
        $key = $this->verifyRateLimitKey($contact, $purpose);

        if (RateLimiter::tooManyAttempts($key, self::MAX_VERIFY_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'code' => ['Too many verification attempts. Please try again in ' . ceil($seconds / 60) . ' minutes.'],
            ]);
        }

        $otp = Otp::where('user_contact_id', $contact->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otp) {
            RateLimiter::hit($key, self::VERIFY_DECAY_MINUTES * 60);
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired verification code.'],
            ]);
        }

        if ($otp->attempts >= self::OTP_MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'code' => ['Too many failed attempts. Please request a new code.'],
            ]);
        }

        if (! $otp->verify($code)) {
            RateLimiter::hit($key, self::VERIFY_DECAY_MINUTES * 60);
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
        return 'otp:send:' . $contact->id . ':' . $purpose;
    }

    protected function resendCooldownKey(UserContact $contact, string $purpose): string
    {
        return 'otp:cooldown:' . $contact->id . ':' . $purpose;
    }

    protected function verifyRateLimitKey(UserContact $contact, string $purpose): string
    {
        return 'otp:verify:' . $contact->id . ':' . $purpose;
    }

    protected function validatePurpose(string $purpose): void
    {
        if (! in_array($purpose, ['verify_contact', 'password_reset'], true)) {
            throw new \InvalidArgumentException("Invalid OTP purpose: {$purpose}");
        }
    }
}
