<?php

namespace App\Domains\Notifications\Jobs;

use App\Domains\Notifications\Mail\CriticalServiceOrderNotificationMail;
use App\Domains\Notifications\Models\NotificationDelivery;
use App\Support\Logging\StructuredLogContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCriticalServiceOrderNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public int $notificationDeliveryId,
        public ?string $correlationId = null,
        public array $context = [],
    ) {}

    public function handle(?StructuredLogContext $structuredLogContext = null): void
    {
        $structuredLogContext ??= app(StructuredLogContext::class);
        $parentContext = $structuredLogContext->all();

        $delivery = NotificationDelivery::query()
            ->with(['company', 'serviceOrder', 'quoteRevision'])
            ->find($this->notificationDeliveryId);

        if ($delivery === null || $delivery->status === NotificationDelivery::STATUS_SENT) {
            return;
        }

        $structuredLogContext->reset();
        $structuredLogContext->ensureCorrelationId($this->correlationId);
        $structuredLogContext->share([
            'job' => self::class,
            'workflow' => 'critical_service_order_notification',
            'notification_delivery_id' => $delivery->getKey(),
            'company_id' => $delivery->company_id,
            'service_order_id' => $delivery->service_order_id,
            'quote_revision_id' => $delivery->quote_revision_id,
            ...$this->context,
        ]);

        Log::info('notification.job.started', [
            'event_type' => $delivery->event_type,
        ]);

        try {
            Mail::to($delivery->recipient_email, $delivery->recipient_name)
                ->send(new CriticalServiceOrderNotificationMail($delivery));

            $delivery->forceFill([
                'status' => NotificationDelivery::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();

            Log::info('notification.job.sent', [
                'event_type' => $delivery->event_type,
                'status' => $delivery->status,
            ]);
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => NotificationDelivery::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => $exception->getMessage(),
            ])->save();

            Log::error('notification.job.failed', [
                'event_type' => $delivery->event_type,
                'status' => $delivery->status,
                'error_message' => $exception->getMessage(),
            ]);
        } finally {
            $structuredLogContext->restore($parentContext);
        }
    }
}
