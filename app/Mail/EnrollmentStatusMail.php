<?php

namespace App\Mail;

use App\Models\CourseEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnrollmentStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CourseEnrollment $enrollment,
        public readonly string $newStatus,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->newStatus) {
            'active'   => 'Your enrollment has been approved — ' . ($this->enrollment->course?->title ?? 'Akuru Institute'),
            'rejected' => 'Enrollment update — ' . ($this->enrollment->course?->title ?? 'Akuru Institute'),
            default    => 'Enrollment update — Akuru Institute',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.enrollment-status');
    }
}
