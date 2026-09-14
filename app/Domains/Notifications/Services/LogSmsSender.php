<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Actions\RecordSmsReceiptAction;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use Illuminate\Support\Facades\Log;

class LogSmsSender implements SmsSenderInterface
{
    /**
     * @var list<array{channel: string, phone: string, body: string, timestamp: string, options: array<string, mixed>}>
     */
    public array $sent = [];

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendSms(string $phoneNumber, string $message, array $options = []): array
    {
        $sentAt = now();
        $record = [
            'channel' => 'sms',
            'phone' => $phoneNumber,
            'body' => $message,
            'timestamp' => $sentAt->toIso8601String(),
            'options' => $options,
        ];
        $this->sent[] = $record;

        Log::info('SMS log sender — not delivered', [
            'channel' => 'sms',
            'to' => $phoneNumber,
            'body' => $message,
            'timestamp' => $record['timestamp'],
            'type' => $options['type'] ?? null,
            'reference' => $options['reference'] ?? null,
            'env' => app()->environment(),
        ]);

        app(RecordSmsReceiptAction::class)->execute([
            'channel' => 'sms',
            'type' => $options['type'] ?? 'notification',
            'reference' => $options['reference'] ?? null,
            'phone' => $phoneNumber,
            'body' => $message,
            'driver' => 'log',
            'success' => true,
            'sent_at' => $sentAt,
        ]);

        return [
            'success' => true,
            'message_id' => 'log_'.uniqid(),
            'status' => 'logged',
            'cost' => 0,
            'driver' => 'log',
        ];
    }

    /**
     * The code reaches the log and `sms_receipts` rather than a handset, which
     * is exactly what a non-production environment needs: somebody testing the
     * login can read the code out of the log.
     *
     * Its absence is what broke OTP login everywhere live SMS is off. Wording
     * mirrors `SmsGatewayService::sendOtp` so the two drivers send the same
     * message.
     *
     * @return array<string, mixed>
     */
    public function sendOtp(string $phoneNumber, string $otp): array
    {
        return $this->sendSms(
            $phoneNumber,
            "Your Akuru Institute verification code is: {$otp}. Valid for 10 minutes. Do not share this code.",
            ['type' => 'otp', 'sender_id' => 'AKURU'],
        );
    }

    public function checkHealth(): bool
    {
        return true;
    }
}
