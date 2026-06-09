<?php

namespace Tests\Feature;

use App\Domains\Auditing\Models\BusinessAudit;
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

class BusinessAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_business_events_share_one_audit_model_and_reads_are_tenant_scoped(): void
    {
        [
            'company_id' => $companyId,
            'manager_token' => $managerToken,
            'finance_token' => $financeToken,
            'technician_token' => $technicianToken,
            'target_membership_id' => $targetMembershipId,
            'service_order_id' => $serviceOrderId,
            'portal_token' => $portalToken,
        ] = $this->createAuditableScenario();

        $quoteDecision = $this->postJson('/api/v1/portal/quotes/'.$portalToken.'/decision', [
            'decision' => 'approve',
        ]);
        $this->assertSame(200, $quoteDecision->status(), $quoteDecision->getContent());

        $deriveCharge = $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/charges/derive-approved-quote');
        $this->assertSame(201, $deriveCharge->status(), $deriveCharge->getContent());

        $payment = $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/payments', [
                'amount_cents' => 20000,
                'payment_method' => 'pix',
                'notes' => 'Entrada inicial auditada.',
            ]);
        $this->assertSame(201, $payment->status(), $payment->getContent());

        $authorization = $this->withToken($managerToken)
            ->patchJson('/api/v1/tenant/memberships/'.$targetMembershipId.'/authorization', [
                'role' => 'manager',
                'permissions' => ['memberships.manage'],
            ]);
        $this->assertSame(200, $authorization->status(), $authorization->getContent());

        $executionStart = $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/start');
        $this->assertSame(200, $executionStart->status(), $executionStart->getContent());

        $executionComplete = $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/complete', [
                'final_test_results' => [
                    ['label' => 'Liga corretamente', 'passed' => true],
                ],
            ]);
        $this->assertSame(200, $executionComplete->status(), $executionComplete->getContent());

        $readyForPickup = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/ready-for-pickup');
        $this->assertSame(200, $readyForPickup->status(), $readyForPickup->getContent());

        $delivery = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/delivery', [
                'received_by_name' => 'Cliente Auditoria',
                'allow_balance_override' => true,
                'balance_override_reason' => 'Acordo aprovado pelo gerente.',
                'notes' => 'Entrega com saldo residual controlado.',
            ]);
        $this->assertSame(200, $delivery->status(), $delivery->getContent());

        $response = $this->withToken($managerToken)->getJson('/api/v1/audit-logs');

        $this->assertSame(200, $response->status(), $response->getContent());

        $response
            ->assertJsonFragment(['event_type' => 'quote_customer_decision_recorded'])
            ->assertJsonFragment(['event_type' => 'service_order_payment_recorded'])
            ->assertJsonFragment(['event_type' => 'membership_authorization_changed'])
            ->assertJsonFragment(['event_type' => 'service_order_delivery_balance_overridden']);

        $events = collect($response->json('data'));

        $this->assertTrue($events->every(fn (array $audit): bool => $audit['company_id'] === $companyId));

        $quoteAudit = BusinessAudit::query()
            ->withoutGlobalScopes()
            ->where('event_type', 'quote_customer_decision_recorded')
            ->firstOrFail();

        $this->assertNull($quoteAudit->actor_membership_id);
        $this->assertSame('approved', $quoteAudit->after_values['status']);

        [$otherManagerToken, $otherCompanyId] = $this->createOtherTenantAuditScenario();

        $otherTenantResponse = $this->withToken($otherManagerToken)->getJson('/api/v1/audit-logs');

        $this->assertSame(200, $otherTenantResponse->status(), $otherTenantResponse->getContent());

        $otherTenantResponse
            ->assertJsonFragment(['company_id' => $otherCompanyId])
            ->assertJsonMissing(['event_type' => 'quote_customer_decision_recorded']);

        $this->assertTrue(
            collect($otherTenantResponse->json('data'))
                ->every(fn (array $audit): bool => $audit['company_id'] === $otherCompanyId)
        );
    }

    /**
     * @return array{
     *   company_id: int,
     *   manager_token: string,
     *   finance_token: string,
     *   technician_token: string,
     *   target_membership_id: int,
     *   service_order_id: int,
     *   portal_token: string
     * }
     */
    protected function createAuditableScenario(): array
    {
        $company = Company::query()->create([
            'name' => 'Acme Auditoria',
            'email' => 'audit@example.com',
            'is_active' => true,
        ]);

        $managerUser = User::query()->create([
            'name' => 'Administrator Audit',
            'email' => 'administrator.audit@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $managerMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $managerUser->getKey(),
            'role' => 'administrator',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $financeUser = User::query()->create([
            'name' => 'Finance Audit',
            'email' => 'finance.audit@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $financeMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $financeUser->getKey(),
            'role' => 'finance',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $technicianUser = User::query()->create([
            'name' => 'Technician Audit',
            'email' => 'technician.audit@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $technicianMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $technicianUser->getKey(),
            'role' => 'technician',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $targetUser = User::query()->create([
            'name' => 'Attendant Audit',
            'email' => 'attendant.audit@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $targetMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $targetUser->getKey(),
            'role' => 'attendant',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'customer_type' => 'pf',
            'name' => 'Cliente Auditoria',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Auditoria',
            'email' => 'cliente.auditoria@example.com',
            'phone' => '(11) 94444-2222',
            'normalized_email' => 'cliente.auditoria@example.com',
            'normalized_phone' => '11944442222',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'HP',
            'model' => 'ProBook 440',
            'serial_number' => 'AUD-'.$company->getKey(),
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
            'audit-administrator-test',
        )['plain_text_token'];

        $financeToken = app(IssueApiToken::class)->handle(
            $financeUser,
            $financeMembership,
            'audit-finance-test',
        )['plain_text_token'];

        $technicianToken = app(IssueApiToken::class)->handle(
            $technicianUser,
            $technicianMembership,
            'audit-technician-test',
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
                'internal_notes' => 'Fonte principal comprometida.',
                'customer_summary' => 'Equipamento precisa de reparo na alimentação.',
            ])->assertOk();

        $quote = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes', [
                'valid_until' => now()->addDay()->toIso8601String(),
                'line_items' => [
                    [
                        'description' => 'Troca de fonte',
                        'quantity' => 1,
                        'unit_price_cents' => 50000,
                    ],
                ],
            ])
            ->assertCreated();

        return [
            'company_id' => $company->getKey(),
            'manager_token' => $managerToken,
            'finance_token' => $financeToken,
            'technician_token' => $technicianToken,
            'target_membership_id' => $targetMembership->getKey(),
            'service_order_id' => $serviceOrderId,
            'portal_token' => $quote->json('data.portal_token'),
        ];
    }

    /**
     * @return array{string, int}
     */
    protected function createOtherTenantAuditScenario(): array
    {
        $company = Company::query()->create([
            'name' => 'Outra Empresa',
            'email' => 'other-audit@example.com',
            'is_active' => true,
        ]);

        $managerUser = User::query()->create([
            'name' => 'Other Administrator Audit',
            'email' => 'other.administrator.audit@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $managerMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $managerUser->getKey(),
            'role' => 'administrator',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $targetUser = User::query()->create([
            'name' => 'Other Target Audit',
            'email' => 'other.target.audit@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $targetMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $targetUser->getKey(),
            'role' => 'attendant',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $managerToken = app(IssueApiToken::class)->handle(
            $managerUser,
            $managerMembership,
            'other-audit-administrator-test',
        )['plain_text_token'];

        $this->withToken($managerToken)
            ->patchJson('/api/v1/tenant/memberships/'.$targetMembership->getKey().'/authorization', [
                'role' => 'manager',
                'permissions' => [],
            ])
            ->assertOk();

        return [$managerToken, $company->getKey()];
    }
}
