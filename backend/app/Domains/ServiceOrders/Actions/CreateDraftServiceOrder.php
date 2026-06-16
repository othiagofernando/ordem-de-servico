<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use Illuminate\Support\Facades\DB;

class CreateDraftServiceOrder
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, ReserveDraftReference $reserveDraftReference): ServiceOrder
    {
        return DB::transaction(function () use ($payload, $reserveDraftReference): ServiceOrder {
            $companyId = currentCompany()?->getKey();
            $draftReference = $reserveDraftReference->handle($companyId);

            $serviceOrder = ServiceOrder::query()->create([
                'company_id' => $companyId,
                'customer_id' => $payload['customer_id'] ?? null,
                'customer_contact_id' => $payload['customer_contact_id'] ?? null,
                'equipment_id' => $payload['equipment_id'] ?? null,
                'intake_checklist_template_id' => $payload['intake_checklist_template_id'] ?? null,
                'reference' => $draftReference,
                'draft_reference' => $draftReference,
                'status' => ServiceOrder::STATUS_DRAFT,
                'title' => $payload['reported_problem'] ?? $payload['equipment_description'] ?? 'Draft intake',
                'equipment_description' => $payload['equipment_description'] ?? null,
                'reported_problem' => $payload['reported_problem'] ?? null,
                'intake_checklist' => $payload['intake_checklist'] ?? null,
            ]);

            app(RecordServiceOrderTimelineEvent::class)->handle(
                $serviceOrder,
                type: 'draft_intake_started',
                title: 'Draft intake started',
                visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
                actorMembership: currentMembership(),
                payload: [
                    'draft_reference' => $draftReference,
                ],
            );

            return $serviceOrder->load(['customer', 'customerContact', 'equipment', 'assignedMembership']);
        });
    }
}
