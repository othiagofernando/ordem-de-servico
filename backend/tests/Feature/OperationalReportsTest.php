<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperationalReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_order_index_supports_practical_search_filters_and_queue_views(): void
    {
        [
            'manager_token' => $managerToken,
            'tech_alpha_membership' => $techAlphaMembership,
            'search_order' => $searchOrder,
            'warranty_return' => $warrantyReturn,
        ] = $this->seedOperationalReportScenario();

        $this->withToken($managerToken)
            ->getJson('/api/v1/service-orders?q=SER-BUSCA-001&status[]=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $searchOrder->getKey())
            ->assertJsonPath('data.0.customer_name', 'Cliente Busca');

        $this->withToken($managerToken)
            ->getJson('/api/v1/service-orders?q=SER-BUSCA-001')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $searchOrder->getKey())
            ->assertJsonPath('data.0.equipment_serial_number', 'SER-BUSCA-001');

        $this->withToken($managerToken)
            ->getJson('/api/v1/service-orders?queue=warranty_returns')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $warrantyReturn->getKey());

        $this->withToken($managerToken)
            ->getJson('/api/v1/service-orders?assigned_membership_id='.$techAlphaMembership->getKey().'&status[]=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $searchOrder->getKey())
            ->assertJsonPath('meta.total', 1);
    }

    public function test_operational_overview_aggregates_respect_visible_scope(): void
    {
        [
            'manager_token' => $managerToken,
            'tech_alpha_token' => $techAlphaToken,
            'tech_alpha_order' => $techAlphaOrder,
        ] = $this->seedOperationalReportScenario();

        $this->withToken($managerToken)
            ->getJson('/api/v1/service-orders/reports/overview')
            ->assertOk()
            ->assertJsonPath('data.totals.service_orders', 4)
            ->assertJsonPath('data.by_status.open', 1)
            ->assertJsonPath('data.by_status.in_progress', 1)
            ->assertJsonPath('data.by_status.ready_for_pickup', 1)
            ->assertJsonPath('data.by_status.delivered', 1)
            ->assertJsonPath('data.quote_outcomes.approved', 1)
            ->assertJsonPath('data.quote_outcomes.rejected', 1)
            ->assertJsonPath('data.quote_outcomes.pending', 1)
            ->assertJsonPath('data.received_amounts.total_received_cents', 25000)
            ->assertJsonPath('data.received_amounts.by_payment_method.pix', 10000)
            ->assertJsonPath('data.received_amounts.by_payment_method.cash', 15000)
            ->assertJsonPath('data.warranty_returns.total', 1)
            ->assertJsonPath('data.warranty_returns.by_decision_status.covered', 1);

        $technicianOverview = $this->withToken($techAlphaToken)
            ->getJson('/api/v1/service-orders/reports/overview')
            ->assertOk()
            ->assertJsonPath('data.totals.service_orders', 2)
            ->assertJsonPath('data.by_status.open', 1)
            ->assertJsonPath('data.by_status.in_progress', 1)
            ->assertJsonMissingPath('data.by_status.ready_for_pickup')
            ->assertJsonPath('data.quote_outcomes.pending', 1)
            ->assertJsonPath('data.received_amounts.total_received_cents', 10000);

        $this->assertSame(
            [$techAlphaOrder->assigned_membership_id],
            collect($technicianOverview->json('data.technician_workload'))
                ->pluck('assigned_membership_id')
                ->filter()
                ->unique()
                ->values()
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function seedOperationalReportScenario(): array
    {
        $company = Company::query()->create([
            'name' => 'Acme Reports',
            'email' => 'reports@example.com',
            'is_active' => true,
        ]);

        $managerUser = User::query()->create([
            'name' => 'Manager Reports',
            'email' => 'manager.reports@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $managerMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $managerUser->getKey(),
            'role' => 'manager',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $techAlphaUser = User::query()->create([
            'name' => 'Tech Alpha',
            'email' => 'tech.alpha@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $techAlphaMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $techAlphaUser->getKey(),
            'role' => 'technician',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $techBetaUser = User::query()->create([
            'name' => 'Tech Beta',
            'email' => 'tech.beta@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $techBetaMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $techBetaUser->getKey(),
            'role' => 'technician',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $managerToken = app(IssueApiToken::class)->handle($managerUser, $managerMembership, 'reports-manager')['plain_text_token'];
        $techAlphaToken = app(IssueApiToken::class)->handle($techAlphaUser, $techAlphaMembership, 'reports-tech-alpha')['plain_text_token'];

        $searchOrder = $this->makeOrder(
            $company,
            $techAlphaMembership,
            [
                'reference' => 'OS-SEARCH-001',
                'status' => ServiceOrder::STATUS_OPEN,
                'customer_name' => 'Cliente Busca',
                'customer_document' => '529.982.247-25',
                'contact_phone' => '(52) 99822-4725',
                'normalized_phone' => '52998224725',
                'serial_number' => 'SER-BUSCA-001',
            ],
        );

        $techAlphaOrder = $this->makeOrder(
            $company,
            $techAlphaMembership,
            [
                'reference' => 'OS-ALPHA-002',
                'status' => ServiceOrder::STATUS_IN_PROGRESS,
                'customer_name' => 'Cliente Alpha',
                'customer_document' => '111.111.111-11',
                'contact_phone' => '(11) 90000-0001',
                'normalized_phone' => '11900000001',
                'serial_number' => 'SER-ALPHA-002',
            ],
        );

        $betaReady = $this->makeOrder(
            $company,
            $techBetaMembership,
            [
                'reference' => 'OS-BETA-003',
                'status' => ServiceOrder::STATUS_READY_FOR_PICKUP,
                'customer_name' => 'Cliente Beta',
                'customer_document' => '222.222.222-22',
                'contact_phone' => '(11) 90000-0002',
                'normalized_phone' => '11900000002',
                'serial_number' => 'SER-BETA-003',
            ],
        );

        $warrantyReturn = $this->makeOrder(
            $company,
            $techBetaMembership,
            [
                'reference' => 'OS-WARRANTY-004',
                'status' => ServiceOrder::STATUS_DELIVERED,
                'customer_name' => 'Cliente Garantia',
                'customer_document' => '333.333.333-33',
                'contact_phone' => '(11) 90000-0003',
                'normalized_phone' => '11900000003',
                'serial_number' => 'SER-WARRANTY-004',
                'warranty_origin_service_order_id' => $betaReady->getKey(),
                'warranty_decision_status' => ServiceOrder::WARRANTY_DECISION_COVERED,
            ],
        );

        QuoteRevision::query()->create([
            'company_id' => $company->getKey(),
            'service_order_id' => $searchOrder->getKey(),
            'revision_number' => 1,
            'status' => QuoteRevision::STATUS_PENDING,
            'line_items' => [['description' => 'Analise', 'quantity' => 1, 'unit_price_cents' => 10000]],
            'currency' => 'BRL',
            'total_cents' => 10000,
            'valid_until' => now()->addDays(2),
            'submitted_at' => now(),
        ]);

        QuoteRevision::query()->create([
            'company_id' => $company->getKey(),
            'service_order_id' => $betaReady->getKey(),
            'revision_number' => 1,
            'status' => QuoteRevision::STATUS_APPROVED,
            'line_items' => [['description' => 'Reparo', 'quantity' => 1, 'unit_price_cents' => 15000]],
            'currency' => 'BRL',
            'total_cents' => 15000,
            'valid_until' => now()->addDays(2),
            'submitted_at' => now(),
            'decisioned_at' => now(),
        ]);

        QuoteRevision::query()->create([
            'company_id' => $company->getKey(),
            'service_order_id' => $warrantyReturn->getKey(),
            'revision_number' => 1,
            'status' => QuoteRevision::STATUS_REJECTED,
            'line_items' => [['description' => 'Garantia', 'quantity' => 1, 'unit_price_cents' => 5000]],
            'currency' => 'BRL',
            'total_cents' => 5000,
            'valid_until' => now()->addDays(2),
            'submitted_at' => now(),
            'decisioned_at' => now(),
        ]);

        ServiceOrderPayment::query()->create([
            'company_id' => $company->getKey(),
            'service_order_id' => $techAlphaOrder->getKey(),
            'received_by_membership_id' => $managerMembership->getKey(),
            'amount_cents' => 10000,
            'currency' => 'BRL',
            'payment_method' => 'pix',
            'received_at' => now(),
            'receipt_snapshot' => ['service_order_reference' => $techAlphaOrder->reference],
        ]);

        ServiceOrderPayment::query()->create([
            'company_id' => $company->getKey(),
            'service_order_id' => $betaReady->getKey(),
            'received_by_membership_id' => $managerMembership->getKey(),
            'amount_cents' => 15000,
            'currency' => 'BRL',
            'payment_method' => 'cash',
            'received_at' => now(),
            'receipt_snapshot' => ['service_order_reference' => $betaReady->reference],
        ]);

        return [
            'manager_token' => $managerToken,
            'tech_alpha_token' => $techAlphaToken,
            'tech_alpha_membership' => $techAlphaMembership,
            'search_order' => $searchOrder,
            'tech_alpha_order' => $techAlphaOrder,
            'warranty_return' => $warrantyReturn,
        ];
    }

    protected function makeOrder(Company $company, Membership $assignedMembership, array $attributes): ServiceOrder
    {
        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'customer_type' => 'pf',
            'name' => $attributes['customer_name'],
            'document' => $attributes['customer_document'],
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => $attributes['customer_name'].' Contato',
            'phone' => $attributes['contact_phone'],
            'normalized_phone' => $attributes['normalized_phone'],
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Dell',
            'model' => 'Latitude',
            'serial_number' => $attributes['serial_number'],
        ]);

        return ServiceOrder::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'customer_contact_id' => $contact->getKey(),
            'equipment_id' => $equipment->getKey(),
            'reference' => $attributes['reference'],
            'title' => 'Ordem '.$attributes['reference'],
            'status' => $attributes['status'],
            'reported_problem' => 'Problema '.$attributes['reference'],
            'assigned_membership_id' => $assignedMembership->getKey(),
            'warranty_origin_service_order_id' => $attributes['warranty_origin_service_order_id'] ?? null,
            'warranty_decision_status' => $attributes['warranty_decision_status'] ?? null,
        ]);
    }
}
