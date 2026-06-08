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
        $payload = $this->delivery->payload?->getArrayCopy() ?? [];

        return new Content(
            view: 'emails.critical-service-order-notification',
            with: [
                'delivery' => $this->delivery,
                'payload' => $payload,
                'companyName' => $payload['brand_name'] ?? $this->delivery->company?->name ?? config('app.name'),
            ],
        );
    }
}
