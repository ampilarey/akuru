<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewContactMessage extends Notification
{
    use Queueable;

    public function __construct(
        public ContactMessage $contactMessage
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
            ->subject('New Contact Message Received')
            ->greeting('New Contact Message')
            ->line('A new contact message has been submitted through the website.')
            ->line('**Name:** ' . $this->contactMessage->name)
            ->when($this->contactMessage->email, fn($mail) => $mail->line('**Email:** ' . $this->contactMessage->email))
            ->when($this->contactMessage->phone, fn($mail) => $mail->line('**Phone:** ' . $this->contactMessage->phone))
            ->line('**Message:**')
            ->line($this->contactMessage->message)
            ->action('View Message', url('/admin/contacts/' . $this->contactMessage->id))
            ->line('Please respond to this inquiry promptly.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'contact_message',
            'message_id' => $this->contactMessage->id,
            'sender_name' => $this->contactMessage->name,
            'sender_email' => $this->contactMessage->email,
            'message' => 'New contact message from ' . $this->contactMessage->name,
        ];
    }
}
