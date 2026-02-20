<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewEnrollmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Payment $payment) {}

    public function envelope(): Envelope
    {
        $course  = $this->payment->items->first()?->course?->title ?? 'Unknown course';
        $student = $this->payment->student?->full_name ?? $this->payment->user?->name ?? 'Unknown';

        return new Envelope(subject: "[New enrollment] {$student} — {$course}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-new-enrollment');
    }
}
