<?php

namespace App\Domains\Website\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DailyContentDigestMail extends Mailable
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(
        public string $subjectLine,
        public array $items,
        public string $unsubscribeUrl,
        public string $language,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-content-digest',
        );
    }
}
