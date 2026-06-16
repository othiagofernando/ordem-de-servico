<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\ServiceOrders\Models\IntakeChecklistTemplate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateDraftServiceOrderPayload
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Request $request, bool $requireMinimumIntakeContext = false): array
    {
        $payload = $request->validate([
            'customer_id' => $requireMinimumIntakeContext ? ['required', 'integer'] : ['nullable', 'integer'],
            'customer_contact_id' => ['nullable', 'integer'],
            'equipment_id' => ['nullable', 'integer'],
            'intake_checklist_template_id' => ['nullable', 'integer'],
            'equipment_description' => $requireMinimumIntakeContext
                ? ['nullable', 'string', 'required_without:equipment_id']
                : ['nullable', 'string'],
            'reported_problem' => $requireMinimumIntakeContext ? ['required', 'string'] : ['nullable', 'string'],
            'intake_checklist' => ['nullable', 'array'],
            'intake_checklist.*.label' => ['required_with:intake_checklist', 'string', 'max:255'],
            'intake_checklist.*.checked' => ['nullable', 'boolean'],
            'intake_checklist.*.notes' => ['nullable', 'string'],
        ]);

        $customer = null;

        if (($payload['customer_id'] ?? null) !== null) {
            $customer = Customer::query()->findOrFail($payload['customer_id']);
            abort_unless($customer->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);
        }

        if (($payload['customer_contact_id'] ?? null) !== null) {
            $contact = CustomerContact::query()->findOrFail($payload['customer_contact_id']);
            abort_unless($contact->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);
            abort_unless($customer !== null && $contact->customer_id === $customer->getKey(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (($payload['equipment_id'] ?? null) !== null) {
            $equipment = Equipment::query()->findOrFail($payload['equipment_id']);
            abort_unless($equipment->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);
            abort_unless($customer !== null && $equipment->customer_id === $customer->getKey(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (($payload['intake_checklist_template_id'] ?? null) !== null) {
            $template = IntakeChecklistTemplate::query()->findOrFail($payload['intake_checklist_template_id']);
            abort_unless($template->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);

            if (($payload['equipment_id'] ?? null) !== null) {
                abort_unless(
                    isset($equipment) && $template->category === $equipment->category,
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
        }

        return $payload;
    }
}
