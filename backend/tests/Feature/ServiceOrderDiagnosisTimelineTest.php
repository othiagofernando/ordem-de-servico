<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\ServiceOrders\Models\IntakeChecklistTemplate;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServiceOrderDiagnosisTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_technician_can_record_and_update_diagnosis_with_timeline_events(): void
    {
        [
            $attendantToken,
            $attendantMembership,
            $technicianToken,
            $technicianMembership,
            $customer,
            $contact,
            $equipment,
        ] = $this->createDiagnosisAccess();

        $template = IntakeChecklistTemplate::query()->withoutGlobalScopes()->create([
            'company_id' => $attendantMembership->company_id,
            'category' => 'Notebook',
            'version' => 1,
            'title' => 'Checklist Notebook',
            'items' => [
                ['key' => 'charger', 'label' => 'Carregador entregue', 'type' => 'boolean', 'required' => true],
            ],
        ]);

        $serviceOrderId = $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'intake_checklist_template_id' => $template->getKey(),
                'equipment_description' => 'Notebook Dell Latitude 5420.',
                'reported_problem' => 'Nao liga.',
                'intake_checklist' => [
                    ['label' => 'Carregador entregue', 'checked' => true],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/drafts/'.$serviceOrderId.'/open')
            ->assertOk();

        ServiceOrder::query()->findOrFail($serviceOrderId)
            ->forceFill([
                'assigned_membership_id' => $technicianMembership->getKey(),
            ])->save();

        $this->withToken($technicianToken)
            ->putJson('/api/v1/service-orders/'.$serviceOrderId.'/diagnosis', [
                'internal_notes' => 'Fonte primaria sem tensao na placa logica.',
                'customer_summary' => 'Necessita analise da etapa de alimentacao.',
            ])
            ->assertOk()
            ->assertJsonPath('data.diagnosis_internal_notes', 'Fonte primaria sem tensao na placa logica.')
            ->assertJsonPath('data.diagnosis_customer_summary', 'Necessita analise da etapa de alimentacao.');

        $this->withToken($technicianToken)
            ->putJson('/api/v1/service-orders/'.$serviceOrderId.'/diagnosis', [
                'internal_notes' => 'CI de carga danificado apos oscilacao eletrica.',
                'customer_summary' => 'Foi identificado defeito na etapa de carga da placa.',
            ])
            ->assertOk()
            ->assertJsonPath('data.diagnosis_internal_notes', 'CI de carga danificado apos oscilacao eletrica.')
            ->assertJsonPath('data.diagnosis_customer_summary', 'Foi identificado defeito na etapa de carga da placa.')
            ->assertJsonPath('data.assigned_membership_id', $technicianMembership->getKey());

        $this->withToken($technicianToken)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId)
            ->assertOk()
            ->assertJsonPath('data.diagnosis_internal_notes', 'CI de carga danificado apos oscilacao eletrica.')
            ->assertJsonPath('data.diagnosis_customer_summary', 'Foi identificado defeito na etapa de carga da placa.');

        $this->withToken($technicianToken)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId.'/timeline')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.type', 'draft_intake_started')
            ->assertJsonPath('data.0.visibility', 'internal')
            ->assertJsonPath('data.1.type', 'service_order_opened')
            ->assertJsonPath('data.1.visibility', 'customer')
            ->assertJsonPath('data.2.type', 'diagnosis_recorded')
            ->assertJsonPath('data.2.visibility', 'internal')
            ->assertJsonPath('data.3.type', 'diagnosis_customer_summary_updated')
            ->assertJsonPath('data.3.visibility', 'customer')
            ->assertJsonPath('data.4.type', 'diagnosis_recorded')
            ->assertJsonPath('data.5.type', 'diagnosis_customer_summary_updated');
    }

    /**
     * @return array{string, Membership, string, Membership, Customer, CustomerContact, Equipment}
     */
    protected function createDiagnosisAccess(): array
    {
        $company = Company::query()->create([
            'name' => 'Acme Assistencia',
            'email' => 'diagnosis@example.com',
            'is_active' => true,
        ]);

        $attendantUser = User::query()->create([
            'name' => 'Attendant Diagnosis',
            'email' => 'attendant.diagnosis@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $attendantMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $attendantUser->getKey(),
            'role' => 'attendant',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $technicianUser = User::query()->create([
            'name' => 'Technician Diagnosis',
            'email' => 'technician.diagnosis@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $technicianMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $technicianUser->getKey(),
            'role' => 'technician',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'customer_type' => 'pf',
            'name' => 'Cliente Diagnostico',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Diagnostico',
            'email' => 'cliente.diagnostico@example.com',
            'phone' => '(11) 98888-7777',
            'normalized_email' => 'cliente.diagnostico@example.com',
            'normalized_phone' => '11988887777',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Dell',
            'model' => 'Latitude 5420',
            'serial_number' => 'DIAG-001-'.$company->getKey(),
        ]);

        $attendantToken = app(IssueApiToken::class)->handle(
            $attendantUser,
            $attendantMembership,
            'service-order-diagnosis-attendant-test',
        )['plain_text_token'];

        $technicianToken = app(IssueApiToken::class)->handle(
            $technicianUser,
            $technicianMembership,
            'service-order-diagnosis-technician-test',
        )['plain_text_token'];

        return [
            $attendantToken,
            $attendantMembership,
            $technicianToken,
            $technicianMembership,
            $customer,
            $contact,
            $equipment,
        ];
    }
}
