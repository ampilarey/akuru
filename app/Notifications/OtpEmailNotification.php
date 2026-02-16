<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $otp,
        public string $purpose,
        public int $minutesValid = 5
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->purpose === 'password_reset'
            ? 'Your password reset code'
            : 'Your verification code';

        $intro = $this->purpose === 'password_reset'
            ? 'You requested a password reset. Use the code below to verify your identity:'
            : 'Use the code below to verify your contact for Akuru Institute:';

        return (new MailMessage)
            ->subject($subject . ' - Akuru Institute')
            ->greeting('Hello!')
            ->line($intro)
            ->line("**{$this->otp}**")
            ->line("This code is valid for {$this->minutesValid} minutes. Do not share it.")
            ->line('If you did not request this, you can safely ignore this email.');
    }
}
