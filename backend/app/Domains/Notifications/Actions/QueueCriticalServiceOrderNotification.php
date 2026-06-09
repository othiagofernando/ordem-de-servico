<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Companies\Actions\ResolveCompanyOperationalSettings;
use App\Domains\Notifications\Jobs\SendCriticalServiceOrderNotificationJob;
use App\Domains\Notifications\Models\NotificationDelivery;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Support\Logging\StructuredLogContext;
use Illuminate\Support\Facades\Log;

class QueueCriticalServiceOrderNotification
{
    public function __construct(
        protected ResolveCompanyOperationalSettings $resolveCompanyOperationalSettings,
        protected StructuredLogContext $structuredLogContext,
    ) {}

    public function handle(
        ServiceOrder $serviceOrder,
        string $eventType,
        array $payload = [],
        ?QuoteRevision $quoteRevision = null,
        ?string $dedupeKey = null,
    ): ?NotificationDelivery {
        $serviceOrder->loadMissing(['company', 'customerContact']);
        $contact = $serviceOrder->customerContact;
        $settings = $this->resolveCompanyOperationalSettings->handle($serviceOrder->company);
        $brandName = $settings->brandName($serviceOrder->company);

        if (! $settings->notificationEnabled($eventType)) {
            return null;
        }

        $payload = [
            'brand_name' => $brandName,
            ...$payload,
        ];

        if ($dedupeKey !== null) {
            $existing = NotificationDelivery::query()->where('dedupe_key', $dedupeKey)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        if (! filled($contact?->email)) {
            return NotificationDelivery::query()->create([
                'company_id' => $serviceOrder->company_id,
                'service_order_id' => $serviceOrder->getKey(),
                'quote_revision_id' => $quoteRevision?->getKey(),
                'event_type' => $eventType,
                'recipient_email' => '',
                'recipient_name' => $contact?->name,
                'subject' => $this->subjectFor($serviceOrder, $eventType, $brandName),
                'payload' => $payload,
                'status' => NotificationDelivery::STATUS_FAILED,
                'error_message' => 'Missing customer contact e-mail.',
                'failed_at' => now(),
                'mailable' => 'critical-service-order-notification',
                'dedupe_key' => $dedupeKey,
            ]);
        }

        $delivery = NotificationDelivery::query()->create([
            'company_id' => $serviceOrder->company_id,
            'service_order_id' => $serviceOrder->getKey(),
            'quote_revision_id' => $quoteRevision?->getKey(),
            'event_type' => $eventType,
            'recipient_email' => $contact->email,
            'recipient_name' => $contact->name,
            'subject' => $this->subjectFor($serviceOrder, $eventType, $brandName),
            'payload' => $payload,
            'status' => NotificationDelivery::STATUS_QUEUED,
            'queued_at' => now(),
            'mailable' => 'critical-service-order-notification',
            'dedupe_key' => $dedupeKey,
        ]);

        Log::info('notification.delivery.queued', [
            'notification_delivery_id' => $delivery->getKey(),
            'event_type' => $delivery->event_type,
            'service_order_id' => $delivery->service_order_id,
            'quote_revision_id' => $delivery->quote_revision_id,
        ]);

        SendCriticalServiceOrderNotificationJob::dispatch(
            $delivery->getKey(),
            $this->structuredLogContext->correlationId(),
            [
                'actor_membership_id' => currentMembership()?->getKey(),
                'actor_user_id' => auth()->id(),
                'company_id' => $serviceOrder->company_id,
                'service_order_id' => $serviceOrder->getKey(),
                'quote_revision_id' => $quoteRevision?->getKey(),
            ],
        );

        return $delivery;
    }

    protected function subjectFor(ServiceOrder $serviceOrder, string $eventType, string $brandName): string
    {
        return match ($eventType) {
            'quote_available' => sprintf('[%s] Orçamento disponível para %s', $brandName, $serviceOrder->reference),
            'quote_expiring' => sprintf('[%s] Orçamento expirando para %s', $brandName, $serviceOrder->reference),
            'ready_for_pickup' => sprintf('[%s] Equipamento pronto para retirada: %s', $brandName, $serviceOrder->reference),
            'payment_receipt' => sprintf('[%s] Recibo de pagamento: %s', $brandName, $serviceOrder->reference),
            default => sprintf('[%s] Atualização da ordem de serviço %s', $brandName, $serviceOrder->reference),
        };
    }
}
