<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\ServiceOrders\Models\IntakeChecklistTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
            'equipment_description' => ['nullable', 'string', 'required_without:equipment_id'],
            'reported_problem' => $requireMinimumIntakeContext ? ['required', 'string'] : ['nullable', 'string'],
            'intake_checklist' => ['nullable', 'array'],
            'intake_checklist.*.label' => ['required_with:intake_checklist', 'string', 'max:255'],
            'intake_checklist.*.checked' => ['nullable', 'boolean'],
            'intake_checklist.*.notes' => ['nullable', 'string'],
        ]);

        $customer = null;

        if (($payload['customer_id'] ?? null) !== null) {
            $customer = Customer::query()->withoutGlobalScopes()->findOrFail($payload['customer_id']);
            $this->ensureBelongsToCurrentCompany(
                $customer->company_id === currentCompany()?->getKey(),
                'customer_id',
                'The selected customer is invalid for the active company.',
            );
        }

        if (($payload['customer_contact_id'] ?? null) !== null) {
            $contact = CustomerContact::query()->withoutGlobalScopes()->findOrFail($payload['customer_contact_id']);
            $this->ensureBelongsToCurrentCompany(
                $contact->company_id === currentCompany()?->getKey(),
                'customer_contact_id',
                'The selected customer contact is invalid for the active company.',
            );
            $this->ensureBelongsToCurrentCompany(
                $customer !== null && $contact->customer_id === $customer->getKey(),
                'customer_contact_id',
                'The selected customer contact does not belong to the selected customer.',
            );
        }

        if (($payload['equipment_id'] ?? null) !== null) {
            $equipment = Equipment::query()->withoutGlobalScopes()->findOrFail($payload['equipment_id']);
            $this->ensureBelongsToCurrentCompany(
                $equipment->company_id === currentCompany()?->getKey(),
                'equipment_id',
                'The selected equipment is invalid for the active company.',
            );
            $this->ensureBelongsToCurrentCompany(
                $customer !== null && $equipment->customer_id === $customer->getKey(),
                'equipment_id',
                'The selected equipment does not belong to the selected customer.',
            );
        }

        if (($payload['intake_checklist_template_id'] ?? null) !== null) {
            $template = IntakeChecklistTemplate::query()->withoutGlobalScopes()->findOrFail($payload['intake_checklist_template_id']);
            $this->ensureBelongsToCurrentCompany(
                $template->company_id === currentCompany()?->getKey(),
                'intake_checklist_template_id',
                'The selected checklist template is invalid for the active company.',
            );

            if (($payload['equipment_id'] ?? null) !== null) {
                $this->ensureBelongsToCurrentCompany(
                    isset($equipment) && $template->category === $equipment->category,
                    'intake_checklist_template_id',
                    'The selected checklist template is incompatible with the selected equipment.',
                );
            }
        }

        return $payload;
    }

    protected function ensureBelongsToCurrentCompany(bool $condition, string $field, string $message): void
    {
        if ($condition) {
            return;
        }

        throw ValidationException::withMessages([$field => $message]);
    }
}
