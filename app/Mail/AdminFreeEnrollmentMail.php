<?php

namespace App\Mail;

use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminFreeEnrollmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly CourseEnrollment $enrollment,
    ) {}

    public function envelope(): Envelope
    {
        $course  = $this->enrollment->course?->title ?? 'Unknown course';
        $student = $this->enrollment->student?->full_name ?? $this->user->name ?? 'Unknown';

        return new Envelope(subject: "[New free enrollment] {$student} — {$course}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-free-enrollment');
    }
}
