<?php

namespace App\Domains\Lending\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A lending notice by email (L1): the same words as the in-app notice, with a link into Akuru. */
class LendingNoticeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $heading,
        public readonly string $body,
        public readonly ?string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading.' — Akuru');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.lending-notice');
    }
}
