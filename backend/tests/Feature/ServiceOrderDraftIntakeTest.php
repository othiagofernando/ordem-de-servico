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
use Tests\Concerns\InteractsWithTenant;
use Tests\TestCase;

class ServiceOrderDraftIntakeTest extends TestCase
{
    use InteractsWithTenant;
    use RefreshDatabase;

    public function test_staff_can_create_resume_and_list_draft_intake_records(): void
    {
        [$token, $membership, $customer, $contact, $equipment] = $this->createAttendantIntakeAccess();

        $createResponse = $this->withToken($token)->postJson('/api/v1/service-orders/drafts', [
            'customer_id' => $customer->getKey(),
            'equipment_id' => $equipment->getKey(),
            'equipment_description' => 'Notebook Dell Latitude 5420 com marcas de uso.',
            'reported_problem' => 'Nao liga apos queda de energia.',
        ]);

        $draftId = $createResponse->assertCreated()
            ->assertJsonPath('data.status', ServiceOrder::STATUS_DRAFT)
            ->assertJsonPath('data.reference', 'DRF-000001')
            ->assertJsonPath('data.draft_reference', 'DRF-000001')
            ->assertJsonPath('data.customer_id', $customer->getKey())
            ->assertJsonPath('data.equipment_id', $equipment->getKey())
            ->json('data.id');

        $this->withToken($token)
            ->putJson('/api/v1/service-orders/drafts/'.$draftId, [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'equipment_description' => 'Notebook Dell Latitude 5420 com marcas de uso.',
                'reported_problem' => 'Nao liga apos queda de energia.',
                'intake_checklist' => [
                    ['label' => 'Carcaca sem avarias graves', 'checked' => true],
                    ['label' => 'Carregador entregue', 'checked' => true, 'notes' => 'Fonte original'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.customer_id', $customer->getKey())
            ->assertJsonPath('data.reported_problem', 'Nao liga apos queda de energia.');

        $this->withToken($token)
            ->getJson('/api/v1/service-orders/'.$draftId)
            ->assertOk()
            ->assertJsonPath('data.reference', 'DRF-000001')
            ->assertJsonPath('data.customer_contact_id', $contact->getKey());

        $this->withToken($token)
            ->getJson('/api/v1/service-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', ServiceOrder::STATUS_DRAFT);

        $this->assertDatabaseHas('service_orders', [
            'id' => $draftId,
            'company_id' => $membership->company_id,
            'draft_reference' => 'DRF-000001',
            'status' => ServiceOrder::STATUS_DRAFT,
        ]);
    }

    public function test_creating_a_draft_requires_minimum_intake_context(): void
    {
        [$token] = $this->createAttendantIntakeAccess();

        $this->withToken($token)
            ->postJson('/api/v1/service-orders/drafts', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer_id',
                'equipment_description',
                'reported_problem',
            ]);
    }

    public function test_opening_is_blocked_until_minimum_intake_data_is_present(): void
    {
        [$token, , $customer] = $this->createAttendantIntakeAccess();

        $draftId = $this->withToken($token)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'equipment_description' => 'Entrada parcial',
                'reported_problem' => 'Falha inicial ainda sem checklist e sem vínculo completo.',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/service-orders/drafts/'.$draftId.'/open')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer_contact_id',
                'equipment_id',
                'intake_checklist_template_id',
                'intake_checklist',
            ]);
    }

    public function test_opening_a_draft_generates_company_scoped_monotonic_order_numbers(): void
    {
        [$token, $membership, $customer, $contact, $equipment] = $this->createAttendantIntakeAccess();
        [$otherToken, $otherMembership, $otherCustomer, $otherContact, $otherEquipment] = $this->createAttendantIntakeAccess(
            email: 'other-intake@example.com',
            companyName: 'Other Intake Co',
        );
        $template = $this->createTemplate($membership->company_id, 'Notebook');
        $otherTemplate = $this->createTemplate($otherMembership->company_id, 'Notebook');

        $firstDraftId = $this->createCompleteDraft($token, $customer, $contact, $equipment, $template)->json('data.id');
        $secondDraftId = $this->createCompleteDraft($token, $customer, $contact, $equipment, $template)->json('data.id');
        $otherDraftId = $this->createCompleteDraft($otherToken, $otherCustomer, $otherContact, $otherEquipment, $otherTemplate)->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/service-orders/drafts/'.$firstDraftId.'/open')
            ->assertOk()
            ->assertJsonPath('data.reference', 'OS-000001')
            ->assertJsonPath('data.draft_reference', 'DRF-000001')
            ->assertJsonPath('data.order_number', 1)
            ->assertJsonPath('data.status', ServiceOrder::STATUS_OPEN);

        $this->withToken($token)
            ->postJson('/api/v1/service-orders/drafts/'.$secondDraftId.'/open')
            ->assertOk()
            ->assertJsonPath('data.reference', 'OS-000002')
            ->assertJsonPath('data.draft_reference', 'DRF-000002')
            ->assertJsonPath('data.order_number', 2);

        $this->withToken($otherToken)
            ->postJson('/api/v1/service-orders/drafts/'.$otherDraftId.'/open')
            ->assertOk()
            ->assertJsonPath('data.reference', 'OS-000001')
            ->assertJsonPath('data.order_number', 1);

        $this->assertDatabaseHas('service_orders', [
            'company_id' => $membership->company_id,
            'draft_reference' => 'DRF-000002',
            'reference' => 'OS-000002',
            'order_number' => 2,
        ]);

        $this->assertDatabaseHas('service_orders', [
            'company_id' => $otherMembership->company_id,
            'draft_reference' => 'DRF-000001',
            'reference' => 'OS-000001',
            'order_number' => 1,
        ]);
    }

    public function test_service_order_show_and_index_are_isolated_by_tenant_with_meta_totals(): void
    {
        [$tokenA, , $customerA, $contactA, $equipmentA] = $this->createAttendantIntakeAccess();
        [$tokenB, , $customerB, $contactB, $equipmentB] = $this->createAttendantIntakeAccess(
            email: 'other-isolation@example.com',
            companyName: 'Other Isolation Co',
        );
        $templateA = $this->createTemplate($customerA->company_id, 'Notebook');
        $templateB = $this->createTemplate($customerB->company_id, 'Notebook');

        $orderIdA = $this->createCompleteDraft($tokenA, $customerA, $contactA, $equipmentA, $templateA)
            ->json('data.id');
        $this->createCompleteDraft($tokenB, $customerB, $contactB, $equipmentB, $templateB);

        $this->withToken($tokenB)
            ->getJson('/api/v1/service-orders/'.$orderIdA)
            ->assertNotFound();

        $this->withToken($tokenA)
            ->getJson('/api/v1/service-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 1);
    }

    public function test_draft_store_rejects_cross_tenant_related_ids_with_422(): void
    {
        [$tokenA, , $customerA] = $this->createAttendantIntakeAccess();
        [, $membershipB, $customerB, $contactB, $equipmentB] = $this->createAttendantIntakeAccess(
            email: 'other-cross@example.com',
            companyName: 'Other Cross Co',
        );
        $templateB = $this->createTemplate($membershipB->company_id, 'Notebook');

        $basePayload = [
            'customer_id' => $customerA->getKey(),
            'equipment_description' => 'Notebook em teste.',
            'reported_problem' => 'Nao liga.',
        ];

        $this->withToken($tokenA)
            ->postJson('/api/v1/service-orders/drafts', [...$basePayload, 'customer_id' => $customerB->getKey()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);

        $this->withToken($tokenA)
            ->postJson('/api/v1/service-orders/drafts', [...$basePayload, 'customer_contact_id' => $contactB->getKey()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_contact_id']);

        $this->withToken($tokenA)
            ->postJson('/api/v1/service-orders/drafts', [...$basePayload, 'equipment_id' => $equipmentB->getKey()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['equipment_id']);

        $this->withToken($tokenA)
            ->postJson('/api/v1/service-orders/drafts', [...$basePayload, 'intake_checklist_template_id' => $templateB->getKey()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['intake_checklist_template_id']);
    }

    public function test_draft_update_rejects_cross_tenant_related_ids_with_422(): void
    {
        [$tokenA, , $customerA, $contactA, $equipmentA] = $this->createAttendantIntakeAccess();
        [, $membershipB, $customerB, $contactB, $equipmentB] = $this->createAttendantIntakeAccess(
            email: 'other-update-cross@example.com',
            companyName: 'Other Update Cross Co',
        );
        $templateA = $this->createTemplate($customerA->company_id, 'Notebook');
        $templateB = $this->createTemplate($membershipB->company_id, 'Notebook');

        $draftId = $this->createCompleteDraft($tokenA, $customerA, $contactA, $equipmentA, $templateA)
            ->json('data.id');

        $basePayload = [
            'customer_id' => $customerA->getKey(),
            'customer_contact_id' => $contactA->getKey(),
            'equipment_id' => $equipmentA->getKey(),
            'intake_checklist_template_id' => $templateA->getKey(),
            'equipment_description' => 'Notebook Dell Latitude 5420 com etiqueta patrimonial.',
            'reported_problem' => 'Nao liga.',
            'intake_checklist' => [
                ['label' => 'Carregador entregue', 'checked' => true],
            ],
        ];

        $this->withToken($tokenA)
            ->putJson('/api/v1/service-orders/drafts/'.$draftId, [...$basePayload, 'customer_id' => $customerB->getKey()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);

        $this->withToken($tokenA)
            ->putJson('/api/v1/service-orders/drafts/'.$draftId, [...$basePayload, 'customer_contact_id' => $contactB->getKey()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_contact_id']);

        $this->withToken($tokenA)
            ->putJson('/api/v1/service-orders/drafts/'.$draftId, [...$basePayload, 'equipment_id' => $equipmentB->getKey()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['equipment_id']);

        $this->withToken($tokenA)
            ->putJson('/api/v1/service-orders/drafts/'.$draftId, [...$basePayload, 'intake_checklist_template_id' => $templateB->getKey()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['intake_checklist_template_id']);
    }

    protected function createCompleteDraft(
        string $token,
        Customer $customer,
        CustomerContact $contact,
        Equipment $equipment,
        IntakeChecklistTemplate $template,
    ) {
        return $this->withToken($token)->postJson('/api/v1/service-orders/drafts', [
            'customer_id' => $customer->getKey(),
            'customer_contact_id' => $contact->getKey(),
            'equipment_id' => $equipment->getKey(),
            'intake_checklist_template_id' => $template->getKey(),
            'equipment_description' => 'Notebook Dell Latitude 5420 com etiqueta patrimonial.',
            'reported_problem' => 'Nao liga.',
            'intake_checklist' => [
                ['label' => 'Carregador entregue', 'checked' => true],
            ],
        ])->assertCreated();
    }

    protected function createTemplate(int $companyId, string $category): IntakeChecklistTemplate
    {
        return IntakeChecklistTemplate::query()->withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'category' => $category,
            'version' => 1,
            'title' => 'Checklist '.$category,
            'items' => [
                ['key' => 'charger', 'label' => 'Carregador entregue', 'type' => 'boolean', 'required' => true],
            ],
        ]);
    }

    /**
     * @return array{string, Membership, Customer, CustomerContact, Equipment}
     */
    protected function createAttendantIntakeAccess(
        string $email = 'intake@example.com',
        string $companyName = 'Acme Assistencia',
    ): array {
        $user = User::query()->create([
            'name' => 'Intake User',
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
            'name' => 'Cliente Intake',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Intake',
            'email' => 'cliente@example.com',
            'phone' => '(11) 90000-1111',
            'normalized_email' => 'cliente@example.com',
            'normalized_phone' => '11900001111',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Dell',
            'model' => 'Latitude 5420',
            'serial_number' => 'INTAKE-001-'.$company->getKey(),
        ]);

        $token = app(IssueApiToken::class)->handle($user, $membership, 'service-order-draft-test')['plain_text_token'];

        return [$token, $membership, $customer, $contact, $equipment];
    }
}
