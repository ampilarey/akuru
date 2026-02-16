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
    protected const MAX_SEND_ATTEMPTS = 5;
    protected const SEND_DECAY_MINUTES = 60;
    protected const MAX_VERIFY_ATTEMPTS = 10;
    protected const VERIFY_DECAY_MINUTES = 15;
    protected const OTP_MAX_ATTEMPTS = 5;

    public function __construct(
        protected SmsGatewayService $smsGateway,
        protected ContactNormalizer $normalizer
    ) {}

    public function send(UserContact $contact, string $purpose): void
    {
        $this->validatePurpose($purpose);
        $key = $this->sendRateLimitKey($contact, $purpose);

        if (RateLimiter::tooManyAttempts($key, self::MAX_SEND_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'contact' => ['Too many OTP requests. Please try again in ' . ceil($seconds / 60) . ' minutes.'],
            ]);
        }

        $code = $this->generateCode();
        $otp = Otp::createForContact($contact, $purpose, $code);

        $channel = $contact->type === 'mobile' ? 'sms' : 'email';
        $minutes = $purpose === 'verify_contact' ? 5 : 15;

        if ($channel === 'sms') {
            $phone = $contact->value;
            if (!str_starts_with($phone, '+')) {
                $phone = $this->normalizer->normalizePhone($contact->value);
            }
            $result = $this->smsGateway->sendOtp($phone, $code);
            if (!($result['success'] ?? false)) {
                throw ValidationException::withMessages([
                    'contact' => ['Unable to send verification code. Please try again.'],
                ]);
            }
        } else {
            Notification::route('mail', $contact->value)
                ->notify(new OtpEmailNotification($code, $purpose, $minutes));
        }

        RateLimiter::hit($key, self::SEND_DECAY_MINUTES * 60);
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

        if (!$otp) {
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

        if (!$otp->verify($code)) {
            RateLimiter::hit($key, self::VERIFY_DECAY_MINUTES * 60);
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code.'],
            ]);
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

    protected function verifyRateLimitKey(UserContact $contact, string $purpose): string
    {
        return 'otp:verify:' . $contact->id . ':' . $purpose;
    }

    protected function validatePurpose(string $purpose): void
    {
        if (!in_array($purpose, ['verify_contact', 'password_reset'], true)) {
            throw new \InvalidArgumentException("Invalid OTP purpose: {$purpose}");
        }
    }
}
