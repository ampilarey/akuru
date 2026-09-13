<?php

namespace App\Mail;

use App\Domains\Admissions\DTOs\FreeEnrollmentNoticeData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminFreeEnrollmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly FreeEnrollmentNoticeData $notice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[New free enrollment] {$this->notice->studentName} — {$this->notice->courseTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-free-enrollment');
    }
}
