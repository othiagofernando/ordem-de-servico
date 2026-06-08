<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\Auth\Models\Membership;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;

class RecordServiceOrderTimelineEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        ServiceOrder $serviceOrder,
        string $type,
        string $title,
        string $visibility = ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
        ?Membership $actorMembership = null,
        array $payload = [],
    ): ServiceOrderTimelineEvent {
        return ServiceOrderTimelineEvent::query()->create([
            'company_id' => $serviceOrder->company_id,
            'service_order_id' => $serviceOrder->getKey(),
            'actor_membership_id' => $actorMembership?->getKey(),
            'type' => $type,
            'visibility' => $visibility,
            'title' => $title,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
