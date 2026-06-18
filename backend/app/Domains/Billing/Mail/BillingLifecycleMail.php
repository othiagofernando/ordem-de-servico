<?php

namespace App\Domains\Billing\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BillingLifecycleMail extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $lines
     */
    public function __construct(
        public string $subjectLine,
        public string $title,
        public string $companyName,
        public array $lines,
        public ?string $actionUrl = null,
        public ?string $actionText = null,
        public ?string $footer = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.billing-lifecycle',
            with: [
                'subjectLine' => $this->subjectLine,
                'title' => $this->title,
                'companyName' => $this->companyName,
                'lines' => $this->lines,
                'actionUrl' => $this->actionUrl,
                'actionText' => $this->actionText,
                'footer' => $this->footer,
            ],
        );
    }
}
