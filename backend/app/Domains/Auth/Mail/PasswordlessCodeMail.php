<?php

namespace App\Domains\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordlessCodeMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $code,
        public string $companyName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Seu código de acesso – {$this->companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.passwordless-code',
            with: [
                'code' => $this->code,
                'companyName' => $this->companyName,
            ],
        );
    }
}
