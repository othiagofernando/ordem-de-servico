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

class ServiceOrderClosureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_is_blocked_when_balance_is_outstanding_unless_privileged_override_is_used(): void
    {
        [$managerToken, $attendantToken, $financeToken, $serviceOrderId] = $this->createReadyForPickupScenario(
            totalPaidCents: 20000,
        );

        $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/delivery', [
                'received_by_name' => 'Cliente Bloqueado',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['outstanding_balance_cents']);

        $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/delivery', [
                'received_by_name' => 'Cliente Bloqueado',
                'allow_balance_override' => true,
                'balance_override_reason' => 'Tentativa sem privilegio.',
            ])
            ->assertForbidden();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/delivery', [
                'received_by_name' => 'Cliente Bloqueado',
                'allow_balance_override' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['balance_override_reason']);

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/delivery', [
                'received_by_name' => 'Cliente Bloqueado',
                'allow_balance_override' => true,
                'balance_override_reason' => 'Liberado pelo gerente apos acordo.',
                'notes' => 'Entrega condicionada a acerto posterior.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ServiceOrder::STATUS_DELIVERED)
            ->assertJsonPath('data.received_by_name', 'Cliente Bloqueado');

        $this->withToken($financeToken)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId.'/financials')
            ->assertOk()
            ->assertJsonPath('data.summary.outstanding_balance_cents', 30000);
    }

    public function test_ready_for_pickup_and_delivery_capture_structured_handoff_evidence(): void
    {
        [$managerToken, $attendantToken, , $serviceOrderId] = $this->createReadyForPickupScenario(
            totalPaidCents: 50000,
        );

        $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/delivery', [
                'received_by_name' => 'Maria Cliente',
                'notes' => 'Equipamento entregue com carregador e bolsa.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ServiceOrder::STATUS_DELIVERED)
            ->assertJsonPath('data.received_by_name', 'Maria Cliente')
            ->assertJsonPath('data.delivery_notes', 'Equipamento entregue com carregador e bolsa.');

        $this->withToken($managerToken)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId.'/timeline')
            ->assertOk()
            ->assertJsonFragment(['type' => 'service_order_ready_for_pickup'])
            ->assertJsonFragment(['type' => 'service_order_delivered']);
    }

    public function test_delivery_can_proceed_with_outstanding_balance_when_company_policy_disables_the_gate(): void
    {
        [$managerToken, $attendantToken, , $serviceOrderId] = $this->createReadyForPickupScenario(
            totalPaidCents: 20000,
        );

        $this->withToken($managerToken)
            ->putJson('/api/v1/company/settings', [
                'block_delivery_with_outstanding_balance' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.finance.block_delivery_with_outstanding_balance', false);

        $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/delivery', [
                'received_by_name' => 'Cliente Flexibilizado',
                'notes' => 'Entrega permitida pela politica operacional da empresa.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ServiceOrder::STATUS_DELIVERED)
            ->assertJsonPath('data.received_by_name', 'Cliente Flexibilizado')
            ->assertJsonPath('data.delivery_notes', 'Entrega permitida pela politica operacional da empresa.');
    }

    public function test_delivery_requires_approved_quote_charge_to_be_derived_when_quote_was_approved(): void
    {
        [$managerToken, $attendantToken, , $serviceOrderId] = $this->createTechnicallyCompletedScenario();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/ready-for-pickup')
            ->assertOk()
            ->assertJsonPath('data.status', ServiceOrder::STATUS_READY_FOR_PICKUP);

        $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/delivery', [
                'received_by_name' => 'Cliente Sem Cobrança Derivada',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['charge_type']);

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/charges/derive-approved-quote')
            ->assertCreated();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/delivery', [
                'received_by_name' => 'Cliente Com Cobrança Derivada',
                'allow_balance_override' => true,
                'balance_override_reason' => 'Entrega autorizada após acerto operacional.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ServiceOrder::STATUS_DELIVERED);
    }

    public function test_terminal_outcomes_support_cancelled_and_no_repair_and_refuse_further_transitions(): void
    {
        [$managerToken, , , $serviceOrderId] = $this->createTechnicallyCompletedScenario();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/close/no-repair', [
                'notes' => 'Cliente recusou prosseguir com o reparo.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ServiceOrder::STATUS_NO_REPAIR)
            ->assertJsonPath('data.terminal_notes', 'Cliente recusou prosseguir com o reparo.');

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/ready-for-pickup')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        [$secondManagerToken, , , $secondServiceOrderId] = $this->createTechnicallyCompletedScenario();

        $this->withToken($secondManagerToken)
            ->postJson('/api/v1/service-orders/'.$secondServiceOrderId.'/close/cancel', [
                'notes' => 'Atendimento cancelado por solicitação do cliente.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ServiceOrder::STATUS_CANCELLED);

        $this->withToken($secondManagerToken)
            ->postJson('/api/v1/service-orders/'.$secondServiceOrderId.'/delivery', [
                'received_by_name' => 'Nao deve entregar',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_assignment_cannot_change_after_execution_has_already_finished(): void
    {
        [$managerToken, , , $completedServiceOrderId] = $this->createTechnicallyCompletedScenario();

        $this->withToken($managerToken)
            ->patchJson('/api/v1/service-orders/'.$completedServiceOrderId.'/assignment', [
                'assigned_membership_id' => null,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        [$secondManagerToken, , , $deliveredServiceOrderId] = $this->createReadyForPickupScenario(
            totalPaidCents: 50000,
        );

        $this->withToken($secondManagerToken)
            ->postJson('/api/v1/service-orders/'.$deliveredServiceOrderId.'/delivery', [
                'received_by_name' => 'Cliente Entregue',
                'notes' => 'Entrega concluida para bloqueio de atribuicao.',
            ])
            ->assertOk();

        $this->withToken($secondManagerToken)
            ->patchJson('/api/v1/service-orders/'.$deliveredServiceOrderId.'/assignment', [
                'assigned_membership_id' => null,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * @return array{string, string, string, int}
     */
    protected function createReadyForPickupScenario(int $totalPaidCents): array
    {
        [$managerToken, $attendantToken, $financeToken, $serviceOrderId] = $this->createTechnicallyCompletedScenario();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/charges/derive-approved-quote')
            ->assertCreated();

        if ($totalPaidCents > 0) {
            $this->withToken($financeToken)
                ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/payments', [
                    'amount_cents' => $totalPaidCents,
                    'payment_method' => 'pix',
                    'notes' => 'Pagamento para cenário de entrega.',
                ])
                ->assertCreated();
        }

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/ready-for-pickup')
            ->assertOk()
            ->assertJsonPath('data.status', ServiceOrder::STATUS_READY_FOR_PICKUP);

        return [$managerToken, $attendantToken, $financeToken, $serviceOrderId];
    }

    /**
     * @return array{string, string, string, int}
     */
    protected function createTechnicallyCompletedScenario(): array
    {
        $suffix = (string) str()->uuid();

        $company = Company::query()->create([
            'name' => 'Acme Encerramento',
            'email' => 'closure-'.$suffix.'@example.com',
            'is_active' => true,
        ]);

        $managerUser = User::query()->create([
            'name' => 'Manager Closure',
            'email' => 'manager.closure.'.$suffix.'@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $managerMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $managerUser->getKey(),
            'role' => 'manager',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $attendantUser = User::query()->create([
            'name' => 'Attendant Closure',
            'email' => 'attendant.closure.'.$suffix.'@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $attendantMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $attendantUser->getKey(),
            'role' => 'attendant',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $financeUser = User::query()->create([
            'name' => 'Finance Closure',
            'email' => 'finance.closure.'.$suffix.'@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $financeMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $financeUser->getKey(),
            'role' => 'finance',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $technicianUser = User::query()->create([
            'name' => 'Technician Closure',
            'email' => 'technician.closure.'.$suffix.'@example.com',
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
            'name' => 'Cliente Encerramento',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Encerramento',
            'email' => 'cliente.encerramento.'.$suffix.'@example.com',
            'phone' => '(11) 96666-1111',
            'normalized_email' => 'cliente.encerramento.'.$suffix.'@example.com',
            'normalized_phone' => '11966661111',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'HP',
            'model' => 'ProBook 440',
            'serial_number' => 'CLOSE-'.$company->getKey(),
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
            'closure-manager-test',
        )['plain_text_token'];

        $attendantToken = app(IssueApiToken::class)->handle(
            $attendantUser,
            $attendantMembership,
            'closure-attendant-test',
        )['plain_text_token'];

        $financeToken = app(IssueApiToken::class)->handle(
            $financeUser,
            $financeMembership,
            'closure-finance-test',
        )['plain_text_token'];

        $technicianToken = app(IssueApiToken::class)->handle(
            $technicianUser,
            $technicianMembership,
            'closure-technician-test',
        )['plain_text_token'];

        $serviceOrderId = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'intake_checklist_template_id' => $template->getKey(),
                'equipment_description' => 'Notebook HP ProBook 440.',
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
            ->forceFill([
                'assigned_membership_id' => $technicianMembership->getKey(),
            ])->save();

        $this->withToken($technicianToken)
            ->putJson('/api/v1/service-orders/'.$serviceOrderId.'/diagnosis', [
                'internal_notes' => 'Placa principal precisa de reparo.',
                'customer_summary' => 'Equipamento precisa de reparo na placa principal.',
            ])
            ->assertOk();

        $quote = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes', [
                'valid_until' => now()->addDay()->toIso8601String(),
                'line_items' => [
                    [
                        'description' => 'Reparo em placa principal',
                        'quantity' => 1,
                        'unit_price_cents' => 50000,
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
                        'label' => 'Inicializacao do sistema',
                        'passed' => true,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ServiceOrder::STATUS_COMPLETED);

        return [$managerToken, $attendantToken, $financeToken, $serviceOrderId];
    }
}
