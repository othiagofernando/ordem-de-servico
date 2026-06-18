<?php

namespace App\Domains\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WelcomeCompanyMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $companyName,
        public string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Bem-vindo ao {$this->companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-company',
            with: [
                'companyName' => $this->companyName,
                'loginUrl' => $this->loginUrl,
            ],
        );
    }
}
