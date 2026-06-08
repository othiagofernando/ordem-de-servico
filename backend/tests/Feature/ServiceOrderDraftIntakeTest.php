<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServiceOrderDraftIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_resume_and_list_draft_intake_records(): void
    {
        [$token, $membership, $customer, $contact, $equipment] = $this->createAttendantIntakeAccess();

        $createResponse = $this->withToken($token)->postJson('/api/v1/service-orders/drafts', []);

        $draftId = $createResponse->assertCreated()
            ->assertJsonPath('data.status', ServiceOrder::STATUS_DRAFT)
            ->assertJsonPath('data.reference', 'DRF-000001')
            ->assertJsonPath('data.draft_reference', 'DRF-000001')
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

    public function test_opening_is_blocked_until_minimum_intake_data_is_present(): void
    {
        [$token] = $this->createAttendantIntakeAccess();

        $draftId = $this->withToken($token)
            ->postJson('/api/v1/service-orders/drafts', [
                'equipment_description' => 'Entrada parcial',
            ])
            ->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/service-orders/drafts/'.$draftId.'/open')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer_id',
                'customer_contact_id',
                'equipment_id',
                'reported_problem',
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

        $firstDraftId = $this->createCompleteDraft($token, $customer, $contact, $equipment)->json('data.id');
        $secondDraftId = $this->createCompleteDraft($token, $customer, $contact, $equipment)->json('data.id');
        $otherDraftId = $this->createCompleteDraft($otherToken, $otherCustomer, $otherContact, $otherEquipment)->json('data.id');

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

    protected function createCompleteDraft(
        string $token,
        Customer $customer,
        CustomerContact $contact,
        Equipment $equipment,
    ) {
        return $this->withToken($token)->postJson('/api/v1/service-orders/drafts', [
            'customer_id' => $customer->getKey(),
            'customer_contact_id' => $contact->getKey(),
            'equipment_id' => $equipment->getKey(),
            'equipment_description' => 'Notebook Dell Latitude 5420 com etiqueta patrimonial.',
            'reported_problem' => 'Nao liga.',
            'intake_checklist' => [
                ['label' => 'Carregador entregue', 'checked' => true],
            ],
        ])->assertCreated();
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
