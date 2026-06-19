<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\ServiceOrders\Models\ServiceOrder;

class SerializeServiceOrder
{
    /**
     * @return array<string, mixed>
     */
    public function handle(ServiceOrder $serviceOrder): array
    {
        $latestQuote = $serviceOrder->relationLoaded('quoteRevisions')
            ? $serviceOrder->quoteRevisions->sortByDesc('revision_number')->first()
            : null;

        $serviceOrder->loadMissing('serviceOrderParts.part');

        return [
            'id' => $serviceOrder->getKey(),
            'reference' => $serviceOrder->reference,
            'draft_reference' => $serviceOrder->draft_reference,
            'order_number' => $serviceOrder->order_number,
            'title' => $serviceOrder->title,
            'status' => $serviceOrder->status,
            'customer_id' => $serviceOrder->customer_id,
            'customer_contact_id' => $serviceOrder->customer_contact_id,
            'equipment_id' => $serviceOrder->equipment_id,
            'equipment_description' => $serviceOrder->equipment_description,
            'reported_problem' => $serviceOrder->reported_problem,
            'intake_checklist' => $serviceOrder->intake_checklist,
            'intake_checklist_template_id' => $serviceOrder->intake_checklist_template_id,
            'intake_checklist_template_version' => $serviceOrder->intake_checklist_template_version,
            'intake_checklist_template_snapshot' => $serviceOrder->intake_checklist_template_snapshot,
            'equipment_snapshot' => $serviceOrder->equipment_snapshot,
            'diagnosis_internal_notes' => $serviceOrder->diagnosis_internal_notes,
            'diagnosis_customer_summary' => $serviceOrder->diagnosis_customer_summary,
            'diagnosis_completed_at' => $serviceOrder->diagnosis_completed_at?->toIso8601String(),
            'technical_completed_at' => $serviceOrder->technical_completed_at?->toIso8601String(),
            'delivered_at' => $serviceOrder->delivered_at?->toIso8601String(),
            'delivered_by_membership_id' => $serviceOrder->delivered_by_membership_id,
            'received_by_name' => $serviceOrder->received_by_name,
            'delivery_notes' => $serviceOrder->delivery_notes,
            'terminalized_at' => $serviceOrder->terminalized_at?->toIso8601String(),
            'terminalized_by_membership_id' => $serviceOrder->terminalized_by_membership_id,
            'terminal_notes' => $serviceOrder->terminal_notes,
            'warranty_origin_service_order_id' => $serviceOrder->warranty_origin_service_order_id,
            'warranty_signal_status' => $serviceOrder->warranty_signal_status,
            'warranty_signal_reason' => $serviceOrder->warranty_signal_reason,
            'warranty_signal_expires_at' => $serviceOrder->warranty_signal_expires_at?->toIso8601String(),
            'warranty_decision_status' => $serviceOrder->warranty_decision_status,
            'warranty_decision_override' => $serviceOrder->warranty_decision_override,
            'warranty_decision_notes' => $serviceOrder->warranty_decision_notes,
            'warranty_decided_at' => $serviceOrder->warranty_decided_at?->toIso8601String(),
            'warranty_decided_by_membership_id' => $serviceOrder->warranty_decided_by_membership_id,
            'opened_at' => $serviceOrder->opened_at?->toIso8601String(),
            'assigned_membership_id' => $serviceOrder->assigned_membership_id,
            'assigned_membership_name' => $serviceOrder->assignedMembership?->user?->name,
            'deleted_at' => $serviceOrder->deleted_at?->toIso8601String(),
            'customer_name' => $serviceOrder->customer?->name,
            'customer_document' => $serviceOrder->customer?->document,
            'customer_contact_name' => $serviceOrder->customerContact?->name,
            'customer_contact_phone' => $serviceOrder->customerContact?->phone,
            'equipment_serial_number' => $serviceOrder->equipment?->serial_number,
            'equipment_brand' => $serviceOrder->equipment?->brand,
            'equipment_model' => $serviceOrder->equipment?->model,
            'latest_quote_status' => $latestQuote?->status,
            'parts' => $serviceOrder->serviceOrderParts->map(fn ($sop) => [
                'id'                    => $sop->getKey(),
                'part_id'               => $sop->part_id,
                'part_name'             => $sop->part?->name,
                'part_sku'              => $sop->part?->sku,
                'part_unit'             => $sop->part?->unit,
                'quantity'              => (float) $sop->quantity,
                'unit_cost_price_cents' => $sop->unit_cost_price_cents,
                'unit_sale_price_cents' => $sop->unit_sale_price_cents,
                'notes'                 => $sop->notes,
            ])->all(),
        ];
    }
}
