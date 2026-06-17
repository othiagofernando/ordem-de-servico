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

class ServiceOrderFinancialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_derive_quote_charges_and_track_adjustments_separately(): void
    {
        [$managerToken, $financeToken, $serviceOrderId] = $this->createApprovedOrderScenario();

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/charges/derive-approved-quote')
            ->assertCreated()
            ->assertJsonPath('data.summary.total_charged_cents', 50000)
            ->assertJsonPath('data.charges.0.charge_type', 'approved_quote')
            ->assertJsonPath('data.charges.0.amount_cents', 50000);

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/charges/adjustments', [
                'description' => 'Desconto comercial aprovado',
                'amount_cents' => -5000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.summary.total_charged_cents', 45000)
            ->assertJsonPath('data.charges.1.charge_type', 'adjustment')
            ->assertJsonPath('data.charges.1.amount_cents', -5000);

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/charges/derive-approved-quote')
            ->assertConflict()
            ->assertJsonPath('message', 'Charges have already been derived from the approved quote.');
    }

    public function test_diagnostic_fee_can_be_recorded_without_repair_approval(): void
    {
        [, $financeToken, $serviceOrderId] = $this->createDiagnosedOpenOrderScenario();

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/charges/diagnostic-fee', [
                'description' => 'Taxa de diagnostico bancada',
                'amount_cents' => 8000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.summary.total_charged_cents', 8000)
            ->assertJsonPath('data.charges.0.charge_type', 'diagnostic_fee')
            ->assertJsonPath('data.charges.0.amount_cents', 8000);
    }

    public function test_partial_payments_generate_immutable_receipt_snapshots(): void
    {
        [$managerToken, $financeToken, $serviceOrderId, $customerId] = $this->createApprovedOrderScenario();

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/charges/derive-approved-quote')
            ->assertCreated();

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/charges/diagnostic-fee', [
                'description' => 'Taxa de diagnostico',
                'amount_cents' => 10000,
            ])
            ->assertCreated();

        $firstPayment = $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/payments', [
                'amount_cents' => 20000,
                'payment_method' => 'cash',
                'notes' => 'Entrada em dinheiro.',
            ]);

        $firstPayment->assertCreated()
            ->assertJsonPath('data.summary.total_paid_cents', 20000)
            ->assertJsonPath('data.summary.outstanding_balance_cents', 40000)
            ->assertJsonPath('data.payments.0.payment_method', 'cash')
            ->assertJsonPath('data.payments.0.receipt_snapshot.customer_name', 'Cliente Financeiro');

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/payments', [
                'amount_cents' => 40000,
                'payment_method' => 'pix',
                'notes' => 'Quitacao via Pix.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.summary.total_paid_cents', 60000)
            ->assertJsonPath('data.summary.outstanding_balance_cents', 0)
            ->assertJsonPath('data.payments.1.payment_method', 'pix');

        Customer::query()->findOrFail($customerId)
            ->forceFill(['name' => 'Cliente Financeiro Alterado'])
            ->save();

        $this->withToken($managerToken)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId.'/financials')
            ->assertOk()
            ->assertJsonPath('data.summary.total_charged_cents', 60000)
            ->assertJsonPath('data.summary.total_paid_cents', 60000)
            ->assertJsonPath('data.payments.0.receipt_snapshot.customer_name', 'Cliente Financeiro')
            ->assertJsonPath('data.payments.0.receipt_snapshot.balance_after_payment_cents', 40000)
            ->assertJsonPath('data.payments.1.receipt_snapshot.balance_after_payment_cents', 0);
    }

    public function test_financial_mutations_are_blocked_for_draft_cancelled_and_no_repair_orders(): void
    {
        [$managerToken, $financeToken, $draftServiceOrderId] = $this->createDraftOrderScenario();

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$draftServiceOrderId.'/financials/charges/diagnostic-fee', [
                'description' => 'Tentativa em rascunho',
                'amount_cents' => 5000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$draftServiceOrderId.'/financials/payments', [
                'amount_cents' => 5000,
                'payment_method' => 'pix',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        [$cancelManagerToken, $cancelFinanceToken, , $cancelledServiceOrderId] = $this->createBaseOrderScenario();

        $this->withToken($cancelManagerToken)
            ->postJson('/api/v1/service-orders/'.$cancelledServiceOrderId.'/close/cancel', [
                'notes' => 'Cancelada antes de seguir no fluxo.',
            ])
            ->assertOk();

        $this->withToken($cancelFinanceToken)
            ->postJson('/api/v1/service-orders/'.$cancelledServiceOrderId.'/financials/charges/adjustments', [
                'description' => 'Tentativa em cancelada',
                'amount_cents' => 1000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        [$noRepairManagerToken, $noRepairFinanceToken, , $noRepairServiceOrderId] = $this->createBaseOrderScenario();

        $this->withToken($noRepairManagerToken)
            ->postJson('/api/v1/service-orders/'.$noRepairServiceOrderId.'/close/no-repair', [
                'notes' => 'Sem viabilidade de reparo.',
            ])
            ->assertOk();

        $this->withToken($noRepairFinanceToken)
            ->postJson('/api/v1/service-orders/'.$noRepairServiceOrderId.'/financials/charges/diagnostic-fee', [
                'description' => 'Tentativa em sem reparo',
                'amount_cents' => 3000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * @return array{string, string, int}
     */
    protected function createDraftOrderScenario(): array
    {
        $suffix = (string) str()->uuid();

        $company = Company::query()->create([
            'name' => 'Acme Assistencia Financeira Draft',
            'email' => 'finance-draft-'.$suffix.'@example.com',
            'is_active' => true,
        ]);

        $managerUser = User::query()->create([
            'name' => 'Manager Finance Draft',
            'email' => 'manager.finance.draft.'.$suffix.'@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $managerMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $managerUser->getKey(),
            'role' => 'manager',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $financeUser = User::query()->create([
            'name' => 'Finance User Draft',
            'email' => 'finance.user.draft.'.$suffix.'@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $financeMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $financeUser->getKey(),
            'role' => 'finance',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'customer_type' => 'pf',
            'name' => 'Cliente Financeiro Draft',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Financeiro Draft',
            'email' => 'cliente.financeiro.draft.'.$suffix.'@example.com',
            'phone' => '(11) 95555-1212',
            'normalized_email' => 'cliente.financeiro.draft.'.$suffix.'@example.com',
            'normalized_phone' => '11955551212',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Lenovo',
            'model' => 'ThinkPad T14',
            'serial_number' => 'FIN-DRAFT-'.$company->getKey().'-'.$suffix,
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
            'finance-manager-draft-test',
        )['plain_text_token'];

        $financeToken = app(IssueApiToken::class)->handle(
            $financeUser,
            $financeMembership,
            'finance-user-draft-test',
        )['plain_text_token'];

        $serviceOrderId = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'intake_checklist_template_id' => $template->getKey(),
                'equipment_description' => 'Notebook Lenovo ThinkPad T14.',
                'reported_problem' => 'Nao liga.',
                'intake_checklist' => [
                    ['label' => 'Carregador entregue', 'checked' => true],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        return [$managerToken, $financeToken, $serviceOrderId];
    }

    /**
     * @return array{string, string, int, int}
     */
    protected function createApprovedOrderScenario(): array
    {
        [$managerToken, $financeToken, $technicianToken, $serviceOrderId, $customerId] = $this->createBaseOrderScenario();

        $this->withToken($technicianToken)
            ->putJson('/api/v1/service-orders/'.$serviceOrderId.'/diagnosis', [
                'internal_notes' => 'Fonte e circuito de carga precisam de reparo.',
                'customer_summary' => 'Equipamento precisa de reparo na etapa de alimentacao.',
            ])
            ->assertOk();

        $quote = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes', [
                'valid_until' => now()->addDay()->toIso8601String(),
                'notes' => 'Orcamento principal.',
                'line_items' => [
                    [
                        'description' => 'Troca de fonte',
                        'quantity' => 1,
                        'unit_price_cents' => 35000,
                    ],
                    [
                        'description' => 'Mao de obra',
                        'quantity' => 1,
                        'unit_price_cents' => 15000,
                    ],
                ],
            ])
            ->assertCreated();

        $this->postJson('/api/v1/portal/quotes/'.$quote->json('data.portal_token').'/decision', [
            'decision' => 'approve',
        ])->assertOk();

        return [$managerToken, $financeToken, $serviceOrderId, $customerId];
    }

    /**
     * @return array{string, string, int}
     */
    protected function createDiagnosedOpenOrderScenario(): array
    {
        [$managerToken, $financeToken, $technicianToken, $serviceOrderId] = $this->createBaseOrderScenario();

        $this->withToken($technicianToken)
            ->putJson('/api/v1/service-orders/'.$serviceOrderId.'/diagnosis', [
                'internal_notes' => 'Somente avaliacao tecnica concluida.',
                'customer_summary' => 'Equipamento avaliado e aguardando decisao.',
            ])
            ->assertOk();

        return [$managerToken, $financeToken, $serviceOrderId];
    }

    /**
     * @return array{string, string, string, int, int}
     */
    protected function createBaseOrderScenario(): array
    {
        $suffix = (string) str()->uuid();

        $company = Company::query()->create([
            'name' => 'Acme Assistencia Financeira',
            'email' => 'finance-'.$suffix.'@example.com',
            'is_active' => true,
        ]);

        $managerUser = User::query()->create([
            'name' => 'Manager Finance',
            'email' => 'manager.finance.'.$suffix.'@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $managerMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $managerUser->getKey(),
            'role' => 'manager',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $financeUser = User::query()->create([
            'name' => 'Finance User',
            'email' => 'finance.user.'.$suffix.'@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $financeMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $financeUser->getKey(),
            'role' => 'finance',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $technicianUser = User::query()->create([
            'name' => 'Technician Finance',
            'email' => 'technician.finance.'.$suffix.'@example.com',
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
            'name' => 'Cliente Financeiro',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Financeiro',
            'email' => 'cliente.financeiro.'.$suffix.'@example.com',
            'phone' => '(11) 95555-1111',
            'normalized_email' => 'cliente.financeiro.'.$suffix.'@example.com',
            'normalized_phone' => '11955551111',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Lenovo',
            'model' => 'ThinkPad T14',
            'serial_number' => 'FIN-'.$company->getKey().'-'.$suffix,
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
            'finance-manager-test',
        )['plain_text_token'];

        $financeToken = app(IssueApiToken::class)->handle(
            $financeUser,
            $financeMembership,
            'finance-user-test',
        )['plain_text_token'];

        $technicianToken = app(IssueApiToken::class)->handle(
            $technicianUser,
            $technicianMembership,
            'finance-technician-test',
        )['plain_text_token'];

        $serviceOrderId = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'intake_checklist_template_id' => $template->getKey(),
                'equipment_description' => 'Notebook Lenovo ThinkPad T14.',
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

        return [
            $managerToken,
            $financeToken,
            $technicianToken,
            $serviceOrderId,
            $customer->getKey(),
        ];
    }
}
