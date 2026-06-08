<?php

namespace App\Domains\Notifications\Mail;

use App\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CriticalServiceOrderNotificationMail extends Mailable
{
    use Queueable;

    public function __construct(public NotificationDelivery $delivery) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->delivery->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.critical-service-order-notification',
            with: [
                'delivery' => $this->delivery,
                'payload' => $this->delivery->payload?->getArrayCopy() ?? [],
                'companyName' => $this->delivery->company?->name ?? config('app.name'),
            ],
        );
    }
}
