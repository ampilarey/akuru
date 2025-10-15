<?php

namespace App\Notifications;

use App\Models\AdmissionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAdmissionApplication extends Notification
{
    use Queueable;

    public function __construct(
        public AdmissionApplication $application
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Course Application Received')
            ->greeting('New Application Alert')
            ->line('A new course application has been submitted.')
            ->line('**Applicant:** ' . $this->application->full_name)
            ->line('**Phone:** ' . $this->application->phone)
            ->when($this->application->email, fn($mail) => $mail->line('**Email:** ' . $this->application->email))
            ->when($this->application->course, fn($mail) => $mail->line('**Course:** ' . $this->application->course->title))
            ->action('View Application', url('/admin/admissions/' . $this->application->id))
            ->line('Please review and respond to this application promptly.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'admission_application',
            'application_id' => $this->application->id,
            'applicant_name' => $this->application->full_name,
            'course_name' => $this->application->course?->title,
            'message' => 'New course application from ' . $this->application->full_name,
        ];
    }
}
