<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\ServiceOrders\Models\ServiceOrder;
use Illuminate\Http\Request;

class UpdateDraftServiceOrder
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(ServiceOrder $serviceOrder, Request $request, array $payload): ServiceOrder
    {
        $attributes = [];

        foreach ([
            'customer_id',
            'customer_contact_id',
            'equipment_id',
            'intake_checklist_template_id',
            'equipment_description',
            'reported_problem',
            'intake_checklist',
        ] as $field) {
            if ($request->exists($field)) {
                $attributes[$field] = $payload[$field] ?? null;
            }
        }

        if ($request->exists('reported_problem') || $request->exists('equipment_description')) {
            $attributes['title'] = $payload['reported_problem'] ?? $payload['equipment_description'] ?? 'Draft intake';
        }

        $serviceOrder->forceFill($attributes)->save();

        return $serviceOrder->load(['customer', 'customerContact', 'equipment', 'assignedMembership']);
    }
}
