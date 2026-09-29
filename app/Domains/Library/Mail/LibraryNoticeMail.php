<?php

namespace App\Domains\Library\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A Digital Library notice by email (STATUS §5lq): the same title and words
 * as the in-app notice, and a button to where it points. Queued, so it needs
 * the queue worker; without it the in-app notice is still there.
 */
class LibraryNoticeMail extends Mailable implements ShouldQueue
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
        return new Envelope(subject: $this->heading.' — Akuru Digital Library');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.library-notice');
    }
}
