<?php

namespace App\Mail;

use App\Domains\Finance\DTOs\PaymentNoticeData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewEnrollmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly PaymentNoticeData $notice) {}

    public function envelope(): Envelope
    {
        // `courses` resolves items, then the enrollment, then the §38 course
        // column — so an engine or manual payment no longer reads
        // "[New enrollment] Yusuf — Unknown course".
        $course = $this->notice->courses[0]['title'] ?? 'Unknown course';

        return new Envelope(subject: "[New enrollment] {$this->notice->studentName} — {$course}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-new-enrollment');
    }
}
