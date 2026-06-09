<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\ServiceOrders\Models\IntakeChecklistTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServiceOrderPrintableViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_fetch_a_printable_service_order_payload_with_two_copies(): void
    {
        [$token, $serviceOrderId, $companyId] = $this->createOpenedServiceOrderScenario();

        $this->withToken($token)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId.'/printable')
            ->assertOk()
            ->assertJsonPath('data.company.name', 'Print Test Company')
            ->assertJsonPath('data.company.document', '12.345.678/0001-01')
            ->assertJsonPath('data.service_order.reference', 'OS-000001')
            ->assertJsonPath('data.service_order.customer.name', 'Cliente Impressao')
            ->assertJsonPath('data.service_order.customer.contact_phone', '(11) 98888-1111')
            ->assertJsonPath('data.service_order.equipment.brand', 'Dell')
            ->assertJsonPath('data.service_order.equipment.serial_number', 'PRINT-001-'.$companyId)
            ->assertJsonPath('data.service_order.intake_checklist.0.label', 'Carregador entregue')
            ->assertJsonPath('data.service_order.signature_blocks.0.label', 'Assinatura do cliente')
            ->assertJsonPath('data.copies.0.label', 'Via da empresa')
            ->assertJsonPath('data.copies.1.label', 'Via do cliente');
    }

    public function test_draft_service_orders_do_not_expose_printable_version(): void
    {
        [$token, $draftId] = $this->createDraftServiceOrderScenario();

        $this->withToken($token)
            ->getJson('/api/v1/service-orders/'.$draftId.'/printable')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Only formally opened service orders can expose a printable version.');
    }

    public function test_printable_service_order_respects_tenant_visibility(): void
    {
        [$tokenA, $serviceOrderId] = $this->createOpenedServiceOrderScenario();
        [$tokenB] = $this->createOpenedServiceOrderScenario(
            companyName: 'Other Print Company',
            companyEmail: 'other-print@example.com',
        );

        $this->withToken($tokenA)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId.'/printable')
            ->assertOk();

        $this->withToken($tokenB)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId.'/printable')
            ->assertNotFound();
    }

    /**
     * @return array{string, int, int}
     */
    protected function createOpenedServiceOrderScenario(
        string $companyName = 'Print Test Company',
        string $companyEmail = 'print@example.com',
    ): array {
        $documentSuffix = str_pad((string) (Company::query()->count() + 1), 2, '0', STR_PAD_LEFT);

        $company = Company::query()->create([
            'name' => $companyName,
            'document' => '12.345.678/0001-'.$documentSuffix,
            'email' => $companyEmail,
            'phone' => '(11) 4000-1000',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Print User',
            'email' => 'user.'.$companyEmail,
            'password' => Hash::make('secret-pass'),
        ]);

        $membership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'attendant',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'customer_type' => 'pf',
            'name' => 'Cliente Impressao',
            'document' => '123.456.789-00',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Impressao',
            'email' => 'cliente.impressao@example.com',
            'phone' => '(11) 98888-1111',
            'normalized_email' => 'cliente.impressao@example.com',
            'normalized_phone' => '11988881111',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Dell',
            'model' => 'Latitude 5520',
            'serial_number' => 'PRINT-001-'.$company->getKey(),
            'accessories' => 'Carregador, mochila',
            'notes' => 'Tampa com pequenos riscos.',
        ]);

        $template = IntakeChecklistTemplate::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'category' => 'Notebook',
            'version' => 1,
            'title' => 'Checklist Notebook',
            'items' => [
                ['key' => 'charger', 'label' => 'Carregador entregue', 'type' => 'boolean', 'required' => true],
                ['key' => 'screen', 'label' => 'Tela sem trincas', 'type' => 'boolean'],
            ],
        ]);

        $token = app(IssueApiToken::class)->handle($user, $membership, 'service-order-printable-test')['plain_text_token'];

        $draftId = $this->withToken($token)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'intake_checklist_template_id' => $template->getKey(),
                'equipment_description' => 'Notebook Dell Latitude 5520 com etiqueta patrimonial.',
                'reported_problem' => 'Nao liga e apresenta odor de queimado.',
                'intake_checklist' => [
                    ['label' => 'Carregador entregue', 'checked' => true, 'notes' => 'Fonte original do cliente'],
                    ['label' => 'Tela sem trincas', 'checked' => true],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $serviceOrderId = $this->withToken($token)
            ->postJson('/api/v1/service-orders/drafts/'.$draftId.'/open')
            ->assertOk()
            ->json('data.id');

        return [$token, $serviceOrderId, $company->getKey()];
    }

    /**
     * @return array{string, int}
     */
    protected function createDraftServiceOrderScenario(): array
    {
        [$token, , $companyId] = $this->createOpenedServiceOrderScenario(
            companyName: 'Draft Print Company',
            companyEmail: 'draft-print@example.com',
        );

        $customer = Customer::query()->where('company_id', $companyId)->firstOrFail();
        $contact = CustomerContact::query()->where('company_id', $companyId)->firstOrFail();
        $equipment = Equipment::query()->where('company_id', $companyId)->firstOrFail();
        $template = IntakeChecklistTemplate::query()->withoutGlobalScopes()->where('company_id', $companyId)->firstOrFail();

        $draftId = $this->withToken($token)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'intake_checklist_template_id' => $template->getKey(),
                'equipment_description' => 'Notebook de triagem ainda nao aberto.',
                'reported_problem' => 'Liga eventualmente.',
                'intake_checklist' => [
                    ['label' => 'Carregador entregue', 'checked' => true],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        return [$token, $draftId];
    }
}
