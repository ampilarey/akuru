<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * SMS Gateway Service
 *
 * Uses Dhiraagu API directly (config from akurusms) when SMS_USE_DHIRAAGU=true.
 * Otherwise falls back to HTTP gateway at akuru.edu.mv.
 */
class SmsGatewayService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected int $timeout = 30;

    public function __construct()
    {
        $this->apiUrl = config('services.sms_gateway.url', 'https://akuru.edu.mv/api/v2');
        $this->apiKey = config('services.sms_gateway.api_key', '');
    }

    /**
     * Send SMS to a single recipient
     *
     * @param string $phoneNumber Phone number (e.g., "7972434" or "9607972434")
     * @param string $message Message content
     * @param array $options Additional options
     * @return array Response with success status and message ID
     */
    public function sendSms(string $phoneNumber, string $message, array $options = []): array
    {
        if ($this->useDhiraagu()) {
            return $this->sendViaDhiraagu($phoneNumber, $message, $options);
        }
        return $this->sendViaHttpGateway($phoneNumber, $message, $options);
    }

    /**
     * Send via Dhiraagu API (config from akurusms)
     */
    protected function sendViaDhiraagu(string $phoneNumber, string $message, array $options = []): array
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($phoneNumber);
            if (!preg_match('/^960\d{7}$/', $phoneNumber)) {
                return [
                    'success' => false,
                    'error' => 'Invalid phone number format for Maldives (e.g. 7820288 or 9607820288)',
                    'error_code' => 'INVALID_PHONE',
                ];
            }

            $username = config('services.dhiraagu.username');
            $password = config('services.dhiraagu.password');
            $apiUrl = config('services.dhiraagu.api_url', 'https://messaging.dhiraagu.com.mv/v1/api/sms');

            if (!$username || !$password) {
                Log::info('SMS Demo Mode - Would send', ['to' => $phoneNumber]);
                return [
                    'success' => true,
                    'message_id' => 'demo_' . uniqid(),
                    'status' => 'sent',
                    'cost' => 0,
                ];
            }

            $authorizationKey = base64_encode($username . ':' . $password);

            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($apiUrl, [
                    'destination' => [$phoneNumber],
                    'content' => $message,
                    'authorizationKey' => $authorizationKey,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $success = isset($data['transactionStatus']) && $data['transactionStatus'] === 'true';
                $messageId = $data['transactionId'] ?? $data['referenceNumber'] ?? null;

                if ($success) {
                    Log::info('SMS sent via Dhiraagu', ['to' => $phoneNumber, 'message_id' => $messageId]);
                    return [
                        'success' => true,
                        'message_id' => $messageId,
                        'status' => 'sent',
                        'cost' => 0,
                    ];
                }

                return [
                    'success' => false,
                    'error' => $data['transactionDescription'] ?? 'SMS delivery failed',
                    'error_code' => 'DHIRAAGU_FAILED',
                ];
            }

            Log::error('Dhiraagu API error', ['status' => $response->status(), 'body' => $response->body()]);
            return [
                'success' => false,
                'error' => $response->body(),
                'error_code' => 'DHIRAAGU_API_ERROR',
            ];
        } catch (\Exception $e) {
            Log::error('Dhiraagu SMS error', ['to' => $phoneNumber, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => 'EXCEPTION',
            ];
        }
    }

    protected function useDhiraagu(): bool
    {
        return config('services.dhiraagu.enabled', false)
            && (config('services.dhiraagu.username') || config('services.dhiraagu.password'));
    }

    /**
     * Send via HTTP gateway (akuru.edu.mv or sms.akuru.edu.mv)
     */
    protected function sendViaHttpGateway(string $phoneNumber, string $message, array $options = []): array
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($phoneNumber);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->apiUrl}/sms/send", [
                    'to' => $phoneNumber,
                    'message' => $message,
                    'sender_id' => $options['sender_id'] ?? 'AKURU',
                    'type' => $options['type'] ?? 'notification',
                ]);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'message_id' => $result['data']['id'] ?? null,
                    'status' => $result['data']['status'] ?? 'sent',
                    'cost' => $result['data']['cost'] ?? 0,
                ];
            }

            $errorJson = $response->json();
            return [
                'success' => false,
                'error' => $errorJson['message'] ?? $response->body(),
                'error_code' => $errorJson['error_code'] ?? 'SMS_FAILED',
            ];
        } catch (\Exception $e) {
            Log::error('SMS gateway error', ['to' => $phoneNumber, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => 'EXCEPTION',
            ];
        }
    }

    /**
     * Send bulk SMS to multiple recipients
     *
     * @param array $recipients Array of phone numbers
     * @param string $message Message content
     * @param array $options Additional options
     * @return array Response with success status and results
     */
    public function sendBulkSms(array $recipients, string $message, array $options = []): array
    {
        try {
            // Format all phone numbers
            $recipients = array_map([$this, 'formatPhoneNumber'], $recipients);

            // Prepare request data
            $data = [
                'recipients' => $recipients,
                'message' => $message,
                'sender_id' => $options['sender_id'] ?? 'AKURU',
                'type' => $options['type'] ?? 'bulk',
            ];

            // Send request
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->apiUrl}/sms/bulk", $data);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info('Bulk SMS sent successfully', [
                    'recipient_count' => count($recipients),
                    'campaign_id' => $result['data']['campaign_id'] ?? null,
                ]);

                return [
                    'success' => true,
                    'campaign_id' => $result['data']['campaign_id'] ?? null,
                    'sent_count' => $result['data']['sent_count'] ?? 0,
                    'failed_count' => $result['data']['failed_count'] ?? 0,
                    'total_cost' => $result['data']['total_cost'] ?? 0,
                ];
            } else {
                Log::error('Bulk SMS sending failed', [
                    'recipient_count' => count($recipients),
                    'status_code' => $response->status(),
                    'response' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => $response->json('message') ?? 'Failed to send bulk SMS',
                    'error_code' => $response->json('error_code') ?? 'BULK_SMS_FAILED',
                ];
            }
        } catch (\Exception $e) {
            Log::error('Bulk SMS service error', [
                'recipient_count' => count($recipients),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => 'EXCEPTION',
            ];
        }
    }

    /**
     * Get SMS status
     *
     * @param string $messageId Message ID from send response
     * @return array Status information
     */
    public function getSmsStatus(string $messageId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->apiUrl}/sms/{$messageId}");

            if ($response->successful()) {
                return $response->json('data') ?? [];
            }

            return [
                'success' => false,
                'error' => 'Failed to get SMS status',
            ];
        } catch (\Exception $e) {
            Log::error('SMS status check error', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get SMS usage statistics
     *
     * @param string|null $period Period (today, week, month)
     * @return array Usage statistics
     */
    public function getUsageStats(?string $period = 'month'): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->apiUrl}/usage", ['period' => $period]);

            if ($response->successful()) {
                return $response->json('data') ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('SMS usage stats error', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check if SMS gateway is available
     *
     * @return bool True if available
     */
    public function checkHealth(): bool
    {
        try {
            $cacheKey = 'sms_gateway_health';
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            $isHealthy = false;
            if ($this->useDhiraagu()) {
                $isHealthy = (bool) (config('services.dhiraagu.username') && config('services.dhiraagu.password'));
            } else {
                $response = Http::timeout(10)->get("{$this->apiUrl}/health");
                $isHealthy = $response->successful();
            }

            Cache::put($cacheKey, $isHealthy, now()->addMinutes(5));
            return $isHealthy;
        } catch (\Exception $e) {
            Log::warning('SMS gateway health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Format phone number to standard format
     *
     * @param string $phoneNumber Phone number
     * @return string Formatted phone number
     */
    protected function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove all non-numeric characters except +
        $phoneNumber = preg_replace('/[^\d+]/', '', $phoneNumber);
        
        // Remove + sign
        $phoneNumber = str_replace('+', '', $phoneNumber);
        
        // If doesn't start with 960, add it (assuming Maldives)
        if (!str_starts_with($phoneNumber, '960')) {
            // If 7 digits, add 960
            if (strlen($phoneNumber) == 7) {
                $phoneNumber = '960' . $phoneNumber;
            }
        }
        
        return $phoneNumber;
    }

    /**
     * Send OTP SMS
     *
     * @param string $phoneNumber Phone number
     * @param string $otp OTP code
     * @return array Response
     */
    public function sendOtp(string $phoneNumber, string $otp): array
    {
        $message = "Your Akuru Institute verification code is: {$otp}. Valid for 10 minutes. Do not share this code.";
        
        return $this->sendSms($phoneNumber, $message, [
            'type' => 'otp',
            'sender_id' => 'AKURU',
        ]);
    }

    /**
     * Send attendance notification
     *
     * @param string $phoneNumber Parent phone number
     * @param string $studentName Student name
     * @param string $status Attendance status (present, absent, late)
     * @param string $date Date
     * @return array Response
     */
    public function sendAttendanceNotification(string $phoneNumber, string $studentName, string $status, string $date): array
    {
        $message = "Akuru Institute: {$studentName} was marked {$status} on {$date}.";
        
        return $this->sendSms($phoneNumber, $message, [
            'type' => 'attendance',
            'reference' => "attendance_{$date}_{$studentName}",
        ]);
    }

    /**
     * Send grade notification
     *
     * @param string $phoneNumber Parent phone number
     * @param string $studentName Student name
     * @param string $subject Subject name
     * @param string $grade Grade
     * @return array Response
     */
    public function sendGradeNotification(string $phoneNumber, string $studentName, string $subject, string $grade): array
    {
        $message = "Akuru Institute: {$studentName} received grade {$grade} in {$subject}.";
        
        return $this->sendSms($phoneNumber, $message, [
            'type' => 'grade',
            'reference' => "grade_{$subject}_{$studentName}",
        ]);
    }

    /**
     * Send announcement broadcast
     *
     * @param array $recipients Array of phone numbers
     * @param string $title Announcement title
     * @param string $message Message content
     * @return array Response
     */
    public function sendAnnouncement(array $recipients, string $title, string $message): array
    {
        $fullMessage = "Akuru Institute - {$title}: {$message}";
        
        return $this->sendBulkSms($recipients, $fullMessage, [
            'type' => 'announcement',
        ]);
    }
}

