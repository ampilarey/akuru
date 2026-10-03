<?php

namespace App\Domains\Admissions\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BACKLOG C16 slice N5: the link a family asked for to finish a course
 * registration later. Admissions' own notification (rule 3: a domain's
 * mail is its own; only the SMS contract is shared). Single-use, good for a day; opening it brings back
 * the courses they chose and asks for a fresh code — it never signs anyone
 * in by itself.
 */
class RegistrationResumeLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $courses
     */
    public function __construct(
        public string $url,
        public array $courses,
        public int $hoursValid = 24,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Finish your course registration - Akuru Institute')
            ->greeting('Hello!')
            ->line('You asked for a link to finish your registration later. Here it is:');

        if ($this->courses !== []) {
            $message->line('Courses: '.implode(', ', $this->courses));
        }

        return $message
            ->action('Finish my registration', $this->url)
            ->line("The link works once and for {$this->hoursValid} hours. You will be asked for a verification code, so nobody who finds the link can use it as you.")
            ->line('If you did not ask for this, you can ignore this email.');
    }
}
