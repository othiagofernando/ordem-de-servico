<?php

namespace App\Domains\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MagicLinkMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $magicLinkUrl,
        public string $companyName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Seu link de acesso – {$this->companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.magic-link',
            with: [
                'magicLinkUrl' => $this->magicLinkUrl,
                'companyName' => $this->companyName,
            ],
        );
    }
}
