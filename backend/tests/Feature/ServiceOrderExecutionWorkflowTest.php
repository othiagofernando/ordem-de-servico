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

class ServiceOrderExecutionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_execution_requires_approved_quote_and_assigned_technician(): void
    {
        [$managerToken, , $technicianToken, $technicianMembership, $serviceOrderId] = $this->createExecutionScenario();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/start')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_membership_id']);

        ServiceOrder::query()->findOrFail($serviceOrderId)
            ->forceFill(['assigned_membership_id' => $technicianMembership->getKey()])
            ->save();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/start')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quote_revision']);
    }

    public function test_execution_records_work_and_requires_final_tests_before_technical_completion(): void
    {
        [$managerToken, , $technicianToken, $technicianMembership, $serviceOrderId, $portalToken] = $this->createExecutionScenario();

        ServiceOrder::query()->findOrFail($serviceOrderId)
            ->forceFill(['assigned_membership_id' => $technicianMembership->getKey()])
            ->save();

        $this->postJson('/api/v1/portal/quotes/'.$portalToken.'/decision', [
            'decision' => 'approve',
        ])->assertOk();

        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/start')
            ->assertOk()
            ->assertJsonPath('data.status', ServiceOrder::STATUS_IN_PROGRESS)
            ->assertJsonPath('data.assigned_membership_id', $technicianMembership->getKey());

        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/complete', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['final_test_results']);

        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/entries', [
                'services' => [
                    [
                        'description' => 'Troca do CI de carga',
                        'quantity' => 1,
                        'notes' => 'Soldagem realizada com estacao de ar.',
                    ],
                ],
                'parts' => [
                    [
                        'description' => 'CI de carga compatível',
                        'quantity' => 1,
                        'notes' => 'Componente novo.',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.execution_entries');

        $complete = $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/complete', [
                'final_test_results' => [
                    [
                        'label' => 'Liga em bateria',
                        'passed' => true,
                    ],
                    [
                        'label' => 'Carregamento em fonte',
                        'passed' => true,
                        'notes' => 'Carga estabilizada.',
                    ],
                ],
            ]);

        $complete->assertOk()
            ->assertJsonPath('data.status', ServiceOrder::STATUS_COMPLETED)
            ->assertJsonPath('data.final_test_results.1.notes', 'Carga estabilizada.');

        $this->assertNotSame(ServiceOrder::STATUS_READY_FOR_PICKUP, $complete->json('data.status'));

        $this->withToken($technicianToken)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId.'/timeline')
            ->assertOk()
            ->assertJsonFragment(['type' => 'execution_started'])
            ->assertJsonFragment(['type' => 'execution_work_recorded'])
            ->assertJsonFragment(['type' => 'technical_completion_recorded']);

        $this->assertDatabaseHas('service_order_execution_entries', [
            'service_order_id' => $serviceOrderId,
            'entry_type' => 'service',
            'description' => 'Troca do CI de carga',
        ]);

        $this->assertDatabaseHas('service_order_execution_entries', [
            'service_order_id' => $serviceOrderId,
            'entry_type' => 'part',
            'description' => 'CI de carga compatível',
        ]);
    }

    /**
     * @return array{string, Membership, string, Membership, int, string}
     */
    protected function createExecutionScenario(): array
    {
        $company = Company::query()->create([
            'name' => 'Acme Assistencia',
            'email' => 'execution@example.com',
            'is_active' => true,
        ]);

        $managerUser = User::query()->create([
            'name' => 'Manager Execution',
            'email' => 'manager.execution@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $managerMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $managerUser->getKey(),
            'role' => 'manager',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $technicianUser = User::query()->create([
            'name' => 'Technician Execution',
            'email' => 'technician.execution@example.com',
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
            'name' => 'Cliente Execucao',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Execucao',
            'email' => 'cliente.execucao@example.com',
            'phone' => '(11) 93333-2222',
            'normalized_email' => 'cliente.execucao@example.com',
            'normalized_phone' => '11933332222',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Dell',
            'model' => 'Latitude 5420',
            'serial_number' => 'EXEC-001-'.$company->getKey(),
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
            'execution-manager-test',
        )['plain_text_token'];

        $technicianToken = app(IssueApiToken::class)->handle(
            $technicianUser,
            $technicianMembership,
            'execution-technician-test',
        )['plain_text_token'];

        $serviceOrderId = $this->withToken($managerToken)
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

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts/'.$serviceOrderId.'/open')
            ->assertOk();

        $this->withToken($managerToken)
            ->putJson('/api/v1/service-orders/'.$serviceOrderId.'/diagnosis', [
                'internal_notes' => 'CI de carga e fonte precisam de reparo.',
                'customer_summary' => 'Equipamento precisa de reparo na etapa de alimentação.',
            ])
            ->assertOk();

        $quote = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes', [
                'valid_until' => now()->addDay()->toIso8601String(),
                'line_items' => [
                    [
                        'description' => 'Troca de fonte',
                        'quantity' => 1,
                        'unit_price_cents' => 35000,
                    ],
                ],
            ])
            ->assertCreated();

        return [
            $managerToken,
            $managerMembership,
            $technicianToken,
            $technicianMembership,
            $serviceOrderId,
            $quote->json('data.portal_token'),
        ];
    }
}
