<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\Auth\Models\Membership;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;

class RecordServiceOrderDiagnosis
{
    /**
     * @param  array{internal_notes: string, customer_summary: string}  $payload
     */
    public function handle(
        ServiceOrder $serviceOrder,
        array $payload,
        Membership $membership,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
    ): ServiceOrder {
        $serviceOrder->forceFill([
            'diagnosis_internal_notes' => $payload['internal_notes'],
            'diagnosis_customer_summary' => $payload['customer_summary'],
            'diagnosis_completed_at' => now(),
        ])->save();

        $recordTimelineEvent->handle(
            $serviceOrder,
            type: 'diagnosis_recorded',
            title: 'Diagnosis recorded',
            visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
            actorMembership: $membership,
        );

        $recordTimelineEvent->handle(
            $serviceOrder,
            type: 'diagnosis_customer_summary_updated',
            title: 'Diagnosis customer summary updated',
            visibility: ServiceOrderTimelineEvent::VISIBILITY_CUSTOMER,
            actorMembership: $membership,
        );

        return $serviceOrder->fresh([
            'customer',
            'customerContact',
            'equipment',
            'assignedMembership',
        ]);
    }
}
