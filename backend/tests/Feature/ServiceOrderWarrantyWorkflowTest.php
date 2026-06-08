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

class ServiceOrderWarrantyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_warranty_return_creates_a_new_linked_service_order_with_initial_signal(): void
    {
        [$managerToken, $originalServiceOrderId] = $this->createDeliveredServiceOrderScenario();

        $response = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$originalServiceOrderId.'/warranty-returns', [
                'reported_problem' => 'Equipamento voltou a desligar sozinho.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', ServiceOrder::STATUS_OPEN)
            ->assertJsonPath('data.warranty_origin_service_order_id', $originalServiceOrderId)
            ->assertJsonPath('data.warranty_signal_status', ServiceOrder::WARRANTY_SIGNAL_ELIGIBLE)
            ->assertJsonPath('data.warranty_decision_status', ServiceOrder::WARRANTY_DECISION_PENDING)
            ->assertJsonPath('data.reported_problem', 'Equipamento voltou a desligar sozinho.');

        $returnOrderId = $response->json('data.id');

        $this->assertNotSame($originalServiceOrderId, $returnOrderId);
        $this->assertNotSame(
            ServiceOrder::query()->findOrFail($originalServiceOrderId)->order_number,
            ServiceOrder::query()->findOrFail($returnOrderId)->order_number,
        );

        $this->assertDatabaseHas('service_order_timeline_events', [
            'service_order_id' => $originalServiceOrderId,
            'type' => 'warranty_return_created',
        ]);

        $this->assertDatabaseHas('service_order_timeline_events', [
            'service_order_id' => $returnOrderId,
            'type' => 'warranty_return_opened',
        ]);
    }

    public function test_warranty_coverage_can_be_confirmed_or_overridden_after_inspection(): void
    {
        [$managerToken, $eligibleOriginalServiceOrderId] = $this->createDeliveredServiceOrderScenario();

        $eligibleReturnOrderId = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$eligibleOriginalServiceOrderId.'/warranty-returns', [
                'reported_problem' => 'Voltou a aquecer excessivamente.',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($managerToken)
            ->patchJson('/api/v1/service-orders/'.$eligibleReturnOrderId.'/warranty-coverage', [
                'decision' => 'covered',
                'notes' => 'Defeito reproduzido dentro da cobertura padrão.',
            ])
            ->assertOk()
            ->assertJsonPath('data.warranty_signal_status', ServiceOrder::WARRANTY_SIGNAL_ELIGIBLE)
            ->assertJsonPath('data.warranty_decision_status', ServiceOrder::WARRANTY_DECISION_COVERED)
            ->assertJsonPath('data.warranty_decision_override', false);

        [$secondManagerToken, $expiredOriginalServiceOrderId] = $this->createDeliveredServiceOrderScenario();

        ServiceOrder::query()->findOrFail($expiredOriginalServiceOrderId)
            ->forceFill([
                'delivered_at' => now()->subDays(120),
            ])
            ->save();

        $expiredReturn = $this->withToken($secondManagerToken)
            ->postJson('/api/v1/service-orders/'.$expiredOriginalServiceOrderId.'/warranty-returns', [
                'reported_problem' => 'Tela voltou a falhar.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.warranty_signal_status', ServiceOrder::WARRANTY_SIGNAL_INELIGIBLE);

        $expiredReturnOrderId = $expiredReturn->json('data.id');

        $this->withToken($secondManagerToken)
            ->patchJson('/api/v1/service-orders/'.$expiredReturnOrderId.'/warranty-coverage', [
                'decision' => 'covered',
                'notes' => 'Cobertura excepcional aprovada após inspeção.',
            ])
            ->assertOk()
            ->assertJsonPath('data.warranty_decision_status', ServiceOrder::WARRANTY_DECISION_COVERED)
            ->assertJsonPath('data.warranty_decision_override', true)
            ->assertJsonPath('data.warranty_decision_notes', 'Cobertura excepcional aprovada após inspeção.');
    }

    /**
     * @return array{string, int}
     */
    protected function createDeliveredServiceOrderScenario(): array
    {
        $suffix = (string) str()->uuid();

        $company = Company::query()->create([
            'name' => 'Acme Garantia',
            'email' => 'warranty-'.$suffix.'@example.com',
            'is_active' => true,
        ]);

        $managerUser = User::query()->create([
            'name' => 'Manager Warranty',
            'email' => 'manager.warranty.'.$suffix.'@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $managerMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $managerUser->getKey(),
            'role' => 'manager',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $financeUser = User::query()->create([
            'name' => 'Finance Warranty',
            'email' => 'finance.warranty.'.$suffix.'@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $financeMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $financeUser->getKey(),
            'role' => 'finance',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $technicianUser = User::query()->create([
            'name' => 'Technician Warranty',
            'email' => 'technician.warranty.'.$suffix.'@example.com',
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
            'name' => 'Cliente Garantia',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Garantia',
            'email' => 'cliente.garantia.'.$suffix.'@example.com',
            'phone' => '(11) 97777-1111',
            'normalized_email' => 'cliente.garantia.'.$suffix.'@example.com',
            'normalized_phone' => '11977771111',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Acer',
            'model' => 'Aspire 5',
            'serial_number' => 'WAR-'.$company->getKey(),
        ]);

        $template = IntakeChecklistTemplate::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'category' => 'Notebook',
            'version' => 1,
            'title' => 'Checklist Notebook',
            'items' => [
                ['key' => 'charger', 'label' => 'Carregador entregue', 'type' => 'boolean', 'required' => true],
            ],
        ]);

        $managerToken = app(IssueApiToken::class)->handle(
            $managerUser,
            $managerMembership,
            'warranty-manager-test',
        )['plain_text_token'];

        $financeToken = app(IssueApiToken::class)->handle(
            $financeUser,
            $financeMembership,
            'warranty-finance-test',
        )['plain_text_token'];

        $technicianToken = app(IssueApiToken::class)->handle(
            $technicianUser,
            $technicianMembership,
            'warranty-technician-test',
        )['plain_text_token'];

        $serviceOrderId = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'intake_checklist_template_id' => $template->getKey(),
                'equipment_description' => 'Notebook Acer Aspire 5.',
                'reported_problem' => 'Nao liga.',
                'intake_checklist' => [
                    ['label' => 'Carregador entregue', 'checked' => true],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts/'.$serviceOrderId.'/open')
            ->assertOk();

        ServiceOrder::query()->findOrFail($serviceOrderId)
            ->forceFill(['assigned_membership_id' => $technicianMembership->getKey()])
            ->save();

        $this->withToken($technicianToken)
            ->putJson('/api/v1/service-orders/'.$serviceOrderId.'/diagnosis', [
                'internal_notes' => 'Reparo em circuito principal necessário.',
                'customer_summary' => 'Equipamento precisa de reparo no circuito principal.',
            ])
            ->assertOk();

        $quote = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes', [
                'valid_until' => now()->addDay()->toIso8601String(),
                'line_items' => [
                    [
                        'description' => 'Reparo em placa',
                        'quantity' => 1,
                        'unit_price_cents' => 45000,
                    ],
                ],
            ])
            ->assertCreated();

        $this->postJson('/api/v1/portal/quotes/'.$quote->json('data.portal_token').'/decision', [
            'decision' => 'approve',
        ])->assertOk();

        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/start')
            ->assertOk();

        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/complete', [
                'final_test_results' => [
                    [
                        'label' => 'Inicialização',
                        'passed' => true,
                    ],
                ],
            ])
            ->assertOk();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/charges/derive-approved-quote')
            ->assertCreated();

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/payments', [
                'amount_cents' => 45000,
                'payment_method' => 'pix',
                'notes' => 'Quitação para cenário de garantia.',
            ])
            ->assertCreated();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/ready-for-pickup')
            ->assertOk();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/delivery', [
                'received_by_name' => 'Cliente Garantia',
                'notes' => 'Entrega original concluída.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ServiceOrder::STATUS_DELIVERED);

        return [$managerToken, $serviceOrderId];
    }
}
