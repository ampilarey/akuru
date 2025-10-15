<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Carbon\Carbon;

class OtpService
{
    protected SmsGatewayService $smsService;

    public function __construct(SmsGatewayService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Generate and send OTP
     *
     * @param string $identifier Phone number or email
     * @param string $type Type of OTP (login, password_reset, verification, 2fa)
     * @param array $options Additional options
     * @return array Response with success status
     */
    public function generate(string $identifier, string $type = 'login', array $options = []): array
    {
        try {
            // Rate limiting - max 3 OTPs per 10 minutes
            $rateLimitKey = "otp_generate:{$identifier}";
            
            if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                
                return [
                    'success' => false,
                    'error' => 'Too many OTP requests. Please try again in ' . ceil($seconds / 60) . ' minutes.',
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                    'retry_after' => $seconds,
                ];
            }

            // Invalidate previous OTPs for this identifier and type
            Otp::forIdentifier($identifier)
                ->ofType($type)
                ->valid()
                ->update(['is_used' => true]);

            // Generate OTP code
            $code = $this->generateCode($options['length'] ?? 6);

            // Create OTP record
            $otp = Otp::create([
                'identifier' => $identifier,
                'code' => $code,
                'type' => $type,
                'expires_at' => now()->addMinutes($options['expires_in'] ?? 10),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Send OTP
            $sent = $this->sendOtp($identifier, $code, $type);

            if (!$sent['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to send OTP: ' . ($sent['error'] ?? 'Unknown error'),
                    'error_code' => 'SMS_FAILED',
                ];
            }

            // Increment rate limiter
            RateLimiter::hit($rateLimitKey, 600); // 10 minutes

            Log::info('OTP generated', [
                'identifier' => $identifier,
                'type' => $type,
                'otp_id' => $otp->id,
            ]);

            return [
                'success' => true,
                'message' => 'OTP sent successfully',
                'expires_at' => $otp->expires_at->toIso8601String(),
                'expires_in_minutes' => $otp->expires_at->diffInMinutes(now()),
            ];

        } catch (\Exception $e) {
            Log::error('OTP generation failed', [
                'identifier' => $identifier,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to generate OTP. Please try again.',
                'error_code' => 'GENERATION_FAILED',
            ];
        }
    }

    /**
     * Verify OTP code
     *
     * @param string $identifier Phone number or email
     * @param string $code OTP code
     * @param string $type Type of OTP
     * @return array Response with success status
     */
    public function verify(string $identifier, string $code, string $type = 'login'): array
    {
        try {
            // Rate limiting - max 5 verification attempts per 5 minutes
            $rateLimitKey = "otp_verify:{$identifier}";
            
            if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                
                return [
                    'success' => false,
                    'error' => 'Too many verification attempts. Please request a new OTP.',
                    'error_code' => 'TOO_MANY_ATTEMPTS',
                ];
            }

            // Find the OTP
            $otp = Otp::forIdentifier($identifier)
                ->ofType($type)
                ->where('code', $code)
                ->latest()
                ->first();

            if (!$otp) {
                RateLimiter::hit($rateLimitKey, 300);
                
                return [
                    'success' => false,
                    'error' => 'Invalid OTP code',
                    'error_code' => 'INVALID_CODE',
                ];
            }

            // Check if already used
            if ($otp->is_used) {
                return [
                    'success' => false,
                    'error' => 'This OTP has already been used',
                    'error_code' => 'ALREADY_USED',
                ];
            }

            // Check if expired
            if ($otp->isExpired()) {
                return [
                    'success' => false,
                    'error' => 'OTP has expired. Please request a new one.',
                    'error_code' => 'EXPIRED',
                ];
            }

            // Check attempt limit
            if ($otp->attempts >= 5) {
                return [
                    'success' => false,
                    'error' => 'Maximum verification attempts exceeded',
                    'error_code' => 'MAX_ATTEMPTS',
                ];
            }

            // Increment attempts
            $otp->incrementAttempts();

            // Verify code
            if ($otp->code !== $code) {
                RateLimiter::hit($rateLimitKey, 300);
                
                return [
                    'success' => false,
                    'error' => 'Invalid OTP code',
                    'error_code' => 'INVALID_CODE',
                    'attempts_remaining' => 5 - $otp->attempts,
                ];
            }

            // Mark as verified
            $otp->markAsVerified();

            // Clear rate limiter
            RateLimiter::clear($rateLimitKey);

            Log::info('OTP verified successfully', [
                'identifier' => $identifier,
                'type' => $type,
                'otp_id' => $otp->id,
            ]);

            return [
                'success' => true,
                'message' => 'OTP verified successfully',
                'otp_id' => $otp->id,
            ];

        } catch (\Exception $e) {
            Log::error('OTP verification failed', [
                'identifier' => $identifier,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to verify OTP. Please try again.',
                'error_code' => 'VERIFICATION_FAILED',
            ];
        }
    }

    /**
     * Send OTP via SMS
     *
     * @param string $identifier Phone number
     * @param string $code OTP code
     * @param string $type Type of OTP
     * @return array Response
     */
    protected function sendOtp(string $identifier, string $code, string $type): array
    {
        // Determine message based on type
        $messages = [
            'login' => "Your Akuru Institute login code is: {$code}. Valid for 10 minutes. Do not share this code.",
            'password_reset' => "Your Akuru Institute password reset code is: {$code}. Valid for 10 minutes. Do not share this code.",
            'verification' => "Your Akuru Institute verification code is: {$code}. Valid for 10 minutes.",
            '2fa' => "Your Akuru Institute 2FA code is: {$code}. Valid for 10 minutes.",
        ];

        $message = $messages[$type] ?? "Your Akuru Institute verification code is: {$code}";

        // Check if identifier is a phone number or email
        if ($this->isPhoneNumber($identifier)) {
            // Send via SMS
            return $this->smsService->sendOtp($identifier, $code);
        } else {
            // Send via email (implement if needed)
            // For now, just log
            Log::info('Email OTP not implemented yet', [
                'identifier' => $identifier,
                'code' => $code,
            ]);

            return [
                'success' => true,
                'message' => 'Email OTP not implemented yet (logged)',
            ];
        }
    }

    /**
     * Generate OTP code
     *
     * @param int $length Length of code
     * @return string OTP code
     */
    protected function generateCode(int $length = 6): string
    {
        $min = pow(10, $length - 1);
        $max = pow(10, $length) - 1;
        
        return (string) random_int($min, $max);
    }

    /**
     * Check if identifier is a phone number
     *
     * @param string $identifier
     * @return bool
     */
    protected function isPhoneNumber(string $identifier): bool
    {
        // Simple check - contains only digits, +, -, spaces
        return preg_match('/^[\d\s\-\+]+$/', $identifier);
    }

    /**
     * Clean up expired OTPs (run via scheduled task)
     */
    public function cleanupExpired(): int
    {
        return Otp::where('expires_at', '<', now()->subHours(24))
            ->delete();
    }

    /**
     * Get OTP statistics for identifier
     *
     * @param string $identifier
     * @return array
     */
    public function getStats(string $identifier): array
    {
        $today = Otp::forIdentifier($identifier)
            ->whereDate('created_at', today())
            ->count();

        $thisWeek = Otp::forIdentifier($identifier)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        $failed = Otp::forIdentifier($identifier)
            ->where('attempts', '>=', 5)
            ->orWhere(function($q) {
                $q->where('is_used', false)
                  ->where('expires_at', '<', now());
            })
            ->count();

        return [
            'today' => $today,
            'this_week' => $thisWeek,
            'failed' => $failed,
        ];
    }
}

