<?php

namespace App\Domains\Notifications\Jobs;

use App\Domains\Notifications\Mail\CriticalServiceOrderNotificationMail;
use App\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCriticalServiceOrderNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $notificationDeliveryId) {}

    public function handle(): void
    {
        $delivery = NotificationDelivery::query()
            ->with(['company', 'serviceOrder', 'quoteRevision'])
            ->find($this->notificationDeliveryId);

        if ($delivery === null || $delivery->status === NotificationDelivery::STATUS_SENT) {
            return;
        }

        try {
            Mail::to($delivery->recipient_email, $delivery->recipient_name)
                ->send(new CriticalServiceOrderNotificationMail($delivery));

            $delivery->forceFill([
                'status' => NotificationDelivery::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => NotificationDelivery::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => $exception->getMessage(),
            ])->save();
        }
    }
}
