<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one delivery of a purchased gift card's code (§43.19: shown once,
 * stored hashed). Carries the plain code through the queue and nowhere
 * else.
 */
class GiftCardCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $plainCode,
        public readonly ?string $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Akuru Institute gift card — '.$this->currency.' '.number_format((float) $this->amount, 2));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.gift-card-code');
    }
}
