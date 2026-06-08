<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Jobs\SendCriticalServiceOrderNotificationJob;
use App\Domains\Notifications\Models\NotificationDelivery;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;

class QueueCriticalServiceOrderNotification
{
    public function handle(
        ServiceOrder $serviceOrder,
        string $eventType,
        array $payload = [],
        ?QuoteRevision $quoteRevision = null,
        ?string $dedupeKey = null,
    ): ?NotificationDelivery {
        $serviceOrder->loadMissing(['company', 'customerContact']);
        $contact = $serviceOrder->customerContact;

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
                'subject' => $this->subjectFor($serviceOrder, $eventType),
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
            'subject' => $this->subjectFor($serviceOrder, $eventType),
            'payload' => $payload,
            'status' => NotificationDelivery::STATUS_QUEUED,
            'queued_at' => now(),
            'mailable' => 'critical-service-order-notification',
            'dedupe_key' => $dedupeKey,
        ]);

        SendCriticalServiceOrderNotificationJob::dispatch($delivery->getKey());

        return $delivery;
    }

    protected function subjectFor(ServiceOrder $serviceOrder, string $eventType): string
    {
        $companyName = $serviceOrder->company?->name ?? config('app.name');

        return match ($eventType) {
            'quote_available' => sprintf('[%s] Orçamento disponível para %s', $companyName, $serviceOrder->reference),
            'quote_expiring' => sprintf('[%s] Orçamento expirando para %s', $companyName, $serviceOrder->reference),
            'ready_for_pickup' => sprintf('[%s] Equipamento pronto para retirada: %s', $companyName, $serviceOrder->reference),
            'payment_receipt' => sprintf('[%s] Recibo de pagamento: %s', $companyName, $serviceOrder->reference),
            default => sprintf('[%s] Atualização da ordem de serviço %s', $companyName, $serviceOrder->reference),
        };
    }
}
