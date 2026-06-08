<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServiceOrderIntakeEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_manage_versioned_checklist_templates_by_category(): void
    {
        [$token] = $this->createIntakeAccess();

        $this->withToken($token)
            ->postJson('/api/v1/intake-checklist-templates', [
                'category' => 'Notebook',
                'title' => 'Checklist Entrada Notebook',
                'items' => [
                    ['key' => 'charger', 'label' => 'Carregador entregue', 'type' => 'boolean', 'required' => true],
                    ['key' => 'screen', 'label' => 'Tela sem rachaduras', 'type' => 'boolean'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 1);

        $this->withToken($token)
            ->postJson('/api/v1/intake-checklist-templates', [
                'category' => 'Notebook',
                'title' => 'Checklist Entrada Notebook v2',
                'items' => [
                    ['key' => 'charger', 'label' => 'Carregador entregue', 'type' => 'boolean', 'required' => true],
                    ['key' => 'screen', 'label' => 'Tela sem rachaduras', 'type' => 'boolean'],
                    ['key' => 'bag', 'label' => 'Bolsa entregue', 'type' => 'boolean'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 2);

        $this->withToken($token)
            ->getJson('/api/v1/intake-checklist-templates?category=Notebook')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.version', 2)
            ->assertJsonPath('data.1.version', 1);
    }

    public function test_opened_service_order_keeps_template_version_and_equipment_snapshot_immutable(): void
    {
        [$token, $membership, $customer, $contact, $equipment] = $this->createIntakeAccess();

        $templateId = $this->withToken($token)
            ->postJson('/api/v1/intake-checklist-templates', [
                'category' => 'Notebook',
                'title' => 'Checklist Entrada Notebook',
                'items' => [
                    ['key' => 'charger', 'label' => 'Carregador entregue', 'type' => 'boolean', 'required' => true],
                    ['key' => 'screen', 'label' => 'Tela sem rachaduras', 'type' => 'boolean'],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $draftId = $this->withToken($token)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'intake_checklist_template_id' => $templateId,
                'equipment_description' => 'Notebook Dell Latitude 5420 com adesivo patrimonial.',
                'reported_problem' => 'Nao liga apos queda de energia.',
                'intake_checklist' => [
                    ['label' => 'Carregador entregue', 'checked' => true],
                    ['label' => 'Tela sem rachaduras', 'checked' => true],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $opened = $this->withToken($token)
            ->postJson('/api/v1/service-orders/drafts/'.$draftId.'/open')
            ->assertOk()
            ->assertJsonPath('data.intake_checklist_template_version', 1)
            ->assertJsonPath('data.intake_checklist_template_snapshot.category', 'Notebook')
            ->assertJsonPath('data.equipment_snapshot.brand', 'Dell')
            ->assertJsonPath('data.equipment_snapshot.model', 'Latitude 5420')
            ->assertJsonPath('data.equipment_snapshot.captured_description', 'Notebook Dell Latitude 5420 com adesivo patrimonial.');

        $serviceOrderId = $opened->json('data.id');

        $this->withToken($token)
            ->putJson('/api/v1/equipments/'.$equipment->getKey(), [
                'customer_id' => $customer->getKey(),
                'category' => 'Notebook',
                'brand' => 'Lenovo',
                'model' => 'ThinkPad T14',
                'serial_number' => $equipment->serial_number,
                'accessories' => 'Dock station',
                'notes' => 'Cadastro mestre alterado',
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/intake-checklist-templates', [
                'category' => 'Notebook',
                'title' => 'Checklist Entrada Notebook v2',
                'items' => [
                    ['key' => 'charger', 'label' => 'Carregador entregue', 'type' => 'boolean', 'required' => true],
                    ['key' => 'screen', 'label' => 'Tela sem rachaduras', 'type' => 'boolean'],
                    ['key' => 'bag', 'label' => 'Bolsa entregue', 'type' => 'boolean'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 2);

        $this->withToken($token)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId)
            ->assertOk()
            ->assertJsonPath('data.intake_checklist_template_version', 1)
            ->assertJsonPath('data.intake_checklist_template_snapshot.version', 1)
            ->assertJsonPath('data.intake_checklist_template_snapshot.items.1.label', 'Tela sem rachaduras')
            ->assertJsonMissing(['label' => 'Bolsa entregue'])
            ->assertJsonPath('data.equipment_snapshot.brand', 'Dell')
            ->assertJsonPath('data.equipment_snapshot.model', 'Latitude 5420');
    }

    /**
     * @return array{string, Membership, Customer, CustomerContact, Equipment}
     */
    protected function createIntakeAccess(
        string $email = 'evidence@example.com',
        string $companyName = 'Acme Assistencia',
    ): array {
        $user = User::query()->create([
            'name' => 'Evidence User',
            'email' => $email,
            'password' => Hash::make('secret-pass'),
        ]);

        $company = Company::query()->create([
            'name' => $companyName,
            'email' => $email,
            'is_active' => true,
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
            'name' => 'Cliente Evidencia',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Evidencia',
            'email' => 'evidence-client@example.com',
            'phone' => '(11) 97777-1111',
            'normalized_email' => 'evidence-client@example.com',
            'normalized_phone' => '11977771111',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Dell',
            'model' => 'Latitude 5420',
            'serial_number' => 'EVIDENCE-001-'.$company->getKey(),
            'accessories' => 'Carregador',
            'notes' => 'Cadastro inicial',
        ]);

        $token = app(IssueApiToken::class)->handle($user, $membership, 'service-order-intake-evidence-test')['plain_text_token'];

        return [$token, $membership, $customer, $contact, $equipment];
    }
}
