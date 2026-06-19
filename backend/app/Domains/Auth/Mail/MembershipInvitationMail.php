<?php

namespace App\Domains\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MembershipInvitationMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $inviteUrl,
        public string $companyName,
        public string $inviteeName,
        public string $roleName,
        public bool $requiresPasswordSetup,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Você foi convidado para o {$this->companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.membership-invitation',
            with: [
                'inviteUrl' => $this->inviteUrl,
                'companyName' => $this->companyName,
                'inviteeName' => $this->inviteeName,
                'roleName' => $this->roleName,
                'requiresPasswordSetup' => $this->requiresPasswordSetup,
            ],
        );
    }
}
