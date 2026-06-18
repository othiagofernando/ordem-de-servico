<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Auditing\Models\BusinessAudit;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\BillingInvoice;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Companies\Models\Company;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_list_manage_and_soft_delete_companies(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => Hash::make('secret-pass'),
            'super_admin' => true,
        ]);

        $token = app(IssueApiToken::class)->handle($superAdmin, null, 'admin-test')['plain_text_token'];

        $company = Company::query()->create([
            'name' => 'Empresa Alvo',
            'document' => '12345678000199',
            'email' => 'alvo@example.com',
            'is_active' => true,
            'status' => Company::STATUS_ACTIVE,
        ]);

        $memberUser = User::query()->create([
            'name' => 'Operator',
            'email' => 'operator@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        Membership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $memberUser->getKey(),
            'role' => 'administrator',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        ServiceOrder::query()->create([
            'company_id' => $company->getKey(),
            'reference' => 'OS-ADMIN-001',
            'title' => 'Notebook Dell',
            'status' => ServiceOrder::STATUS_OPEN,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/admin/companies?q=Alvo')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Empresa Alvo')
            ->assertJsonPath('data.0.memberships_count', 1)
            ->assertJsonPath('data.0.service_orders_count', 1)
            ->assertJsonPath('summary.active_companies', 1);

        $this->withToken($token)
            ->getJson('/api/v1/admin/companies/'.$company->getKey())
            ->assertOk()
            ->assertJsonPath('data.status', Company::STATUS_ACTIVE)
            ->assertJsonPath('data.tenant_url', 'http://localhost:5173/empresa-alvo')
            ->assertJsonCount(0, 'data.recent_invoices');

        $this->withToken($token)
            ->patchJson('/api/v1/admin/companies/'.$company->getKey().'/suspend')
            ->assertOk()
            ->assertJsonPath('data.status', Company::STATUS_SUSPENDED);

        $this->withToken($token)
            ->getJson('/api/v1/admin/companies/'.$company->getKey().'/audits')
            ->assertOk()
            ->assertJsonPath('data.0.event_type', 'company.suspended')
            ->assertJsonPath('data.0.actor_type', 'super_admin')
            ->assertJsonPath('data.0.actor_name', 'Root');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'operator@example.com',
            'password' => 'secret-pass',
        ])->assertForbidden();

        $this->withToken($token)
            ->patchJson('/api/v1/admin/companies/'.$company->getKey().'/activate')
            ->assertOk()
            ->assertJsonPath('data.status', Company::STATUS_ACTIVE);

        $this->withToken($token)
            ->deleteJson('/api/v1/admin/companies/'.$company->getKey(), [
                'confirmation' => 'Empresa Alvo',
            ])->assertNoContent();

        $this->assertSoftDeleted('companies', [
            'id' => $company->getKey(),
        ]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->getKey(),
            'status' => Company::STATUS_CANCELLED,
            'is_active' => false,
        ]);
    }

    public function test_super_admin_can_create_and_update_company_from_admin_panel(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => Hash::make('secret-pass'),
            'super_admin' => true,
        ]);

        $token = app(IssueApiToken::class)->handle($superAdmin, null, 'admin-company-form')['plain_text_token'];

        $createResponse = $this->withToken($token)
            ->postJson('/api/v1/admin/companies', [
                'name' => 'Empresa Criada',
                'trade_name' => 'Empresa XPTO',
                'document' => '11.222.333/0001-44',
                'email' => 'empresa-criada@example.com',
                'phone' => '(11) 99999-9999',
                'website' => 'https://empresa-criada.test',
                'address_line_1' => 'Rua A, 100',
                'address_line_2' => 'Sala 5',
                'district' => 'Centro',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'postal_code' => '01000-000',
                'status' => Company::STATUS_ACTIVE,
                'is_active' => true,
                'passwordless_code_enabled' => true,
                'magic_link_enabled' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Empresa Criada')
            ->assertJsonPath('data.status', Company::STATUS_ACTIVE);

        $companyId = $createResponse->json('data.id');

        $this->assertDatabaseHas('companies', [
            'id' => $companyId,
            'trade_name' => 'Empresa XPTO',
            'city' => 'Sao Paulo',
            'passwordless_code_enabled' => true,
            'magic_link_enabled' => true,
        ]);

        $this->assertDatabaseHas('company_service_order_counters', [
            'company_id' => $companyId,
            'next_draft_number' => 1,
            'next_service_order_number' => 1,
        ]);

        $this->assertDatabaseHas('company_operational_settings', [
            'company_id' => $companyId,
        ]);

        $this->withToken($token)
            ->putJson('/api/v1/admin/companies/'.$companyId, [
                'name' => 'Empresa Editada',
                'trade_name' => 'Empresa Editada LTDA',
                'document' => '11.222.333/0001-44',
                'email' => 'empresa-editada@example.com',
                'phone' => '(11) 98888-7777',
                'website' => 'https://empresa-editada.test',
                'address_line_1' => 'Rua B, 200',
                'address_line_2' => null,
                'district' => 'Bela Vista',
                'city' => 'Campinas',
                'state' => 'SP',
                'postal_code' => '13000-000',
                'status' => Company::STATUS_SUSPENDED,
                'is_active' => false,
                'passwordless_code_enabled' => false,
                'magic_link_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Empresa Editada')
            ->assertJsonPath('data.status', Company::STATUS_SUSPENDED);

        $this->withToken($token)
            ->getJson('/api/v1/admin/companies/'.$companyId)
            ->assertOk()
            ->assertJsonPath('data.trade_name', 'Empresa Editada LTDA')
            ->assertJsonPath('data.city', 'Campinas')
            ->assertJsonPath('data.passwordless_code_enabled', false)
            ->assertJsonPath('data.magic_link_enabled', false);

        $this->assertDatabaseHas('business_audits', [
            'company_id' => $companyId,
            'category' => 'admin',
            'event_type' => 'company.created',
        ]);

        $this->assertDatabaseHas('business_audits', [
            'company_id' => $companyId,
            'category' => 'admin',
            'event_type' => 'company.updated',
        ]);

        $audit = BusinessAudit::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('event_type', 'company.updated')
            ->latest('id')
            ->firstOrFail();

        $this->withToken($token)
            ->getJson('/api/v1/admin/audits/'.$audit->getKey())
            ->assertOk()
            ->assertJsonPath('data.company_id', $companyId)
            ->assertJsonPath('data.event_type', 'company.updated')
            ->assertJsonPath('data.category', 'admin');
    }

    public function test_super_admin_can_list_plans_and_filter_companies_by_plan(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => Hash::make('secret-pass'),
            'super_admin' => true,
        ]);

        $token = app(IssueApiToken::class)->handle($superAdmin, null, 'admin-plans')['plain_text_token'];

        $starter = Plan::query()->create([
            'slug' => 'starter-admin-test',
            'name' => 'Starter Admin Test',
            'max_users' => 3,
            'max_service_orders_per_month' => 50,
            'max_attachments_storage_mb' => 512,
            'reference_price_cents' => 9900,
        ]);

        $enterprise = Plan::query()->create([
            'slug' => 'enterprise-admin-test',
            'name' => 'Enterprise Admin Test',
            'max_users' => null,
            'max_service_orders_per_month' => null,
            'max_attachments_storage_mb' => 4096,
            'reference_price_cents' => 49900,
        ]);

        $starterCompany = Company::query()->create([
            'name' => 'Empresa Starter',
            'email' => 'starter@example.com',
            'is_active' => true,
            'status' => Company::STATUS_ACTIVE,
        ]);

        $enterpriseCompany = Company::query()->create([
            'name' => 'Empresa Enterprise',
            'email' => 'enterprise@example.com',
            'is_active' => true,
            'status' => Company::STATUS_ACTIVE,
        ]);

        Subscription::query()->create([
            'company_id' => $starterCompany->getKey(),
            'plan_id' => $starter->getKey(),
            'started_at' => now()->subDays(2),
            'expires_at' => now()->addDays(10),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        Subscription::query()->create([
            'company_id' => $enterpriseCompany->getKey(),
            'plan_id' => $enterprise->getKey(),
            'started_at' => now()->subDays(4),
            'expires_at' => now()->addDays(45),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/admin/plans')
            ->assertOk()
            ->assertJsonFragment([
                'slug' => 'starter-admin-test',
                'name' => 'Starter Admin Test',
                'subscribed_companies_count' => 1,
                'active_companies_count' => 1,
                'expiring_next_15_days_count' => 1,
            ])
            ->assertJsonFragment([
                'slug' => 'enterprise-admin-test',
                'name' => 'Enterprise Admin Test',
                'subscribed_companies_count' => 1,
                'active_companies_count' => 1,
                'expiring_next_15_days_count' => 0,
            ]);

        $this->withToken($token)
            ->getJson('/api/v1/admin/companies?plan_id='.$starter->getKey())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Empresa Starter');
    }

    public function test_super_admin_can_create_and_update_plans(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => Hash::make('secret-pass'),
            'super_admin' => true,
        ]);

        $token = app(IssueApiToken::class)->handle($superAdmin, null, 'admin-plans-form')['plain_text_token'];

        $response = $this->withToken($token)
            ->postJson('/api/v1/admin/plans', [
                'slug' => 'plano-cadastro',
                'name' => 'Plano Cadastro',
                'max_users' => 8,
                'max_service_orders_per_month' => 250,
                'max_attachments_storage_mb' => 2048,
                'reference_price_cents' => 24900,
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'plano-cadastro')
            ->assertJsonPath('data.name', 'Plano Cadastro');

        $planId = $response->json('data.id');

        $this->assertDatabaseHas('plans', [
            'id' => $planId,
            'slug' => 'plano-cadastro',
            'reference_price_cents' => 24900,
        ]);

        $this->withToken($token)
            ->putJson('/api/v1/admin/plans/'.$planId, [
                'slug' => 'plano-editado',
                'name' => 'Plano Editado',
                'max_users' => 10,
                'max_service_orders_per_month' => 300,
                'max_attachments_storage_mb' => 4096,
                'reference_price_cents' => 29900,
            ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'plano-editado')
            ->assertJsonPath('data.name', 'Plano Editado');

        $this->withToken($token)
            ->getJson('/api/v1/admin/plans/'.$planId)
            ->assertOk()
            ->assertJsonPath('data.reference_price_cents', 29900)
            ->assertJsonPath('data.limits.max_users', 10);
    }

    public function test_super_admin_listing_includes_cancelled_soft_deleted_companies(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => Hash::make('secret-pass'),
            'super_admin' => true,
        ]);

        $token = app(IssueApiToken::class)->handle($superAdmin, null, 'admin-test')['plain_text_token'];

        $cancelled = Company::query()->create([
            'name' => 'Empresa Cancelada',
            'email' => 'cancelada@example.com',
            'is_active' => false,
            'status' => Company::STATUS_CANCELLED,
        ]);

        $cancelled->delete();

        $this->withToken($token)
            ->getJson('/api/v1/admin/companies')
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Empresa Cancelada',
                'status' => Company::STATUS_CANCELLED,
            ]);
    }

    public function test_non_super_admin_gets_strict_404_on_admin_routes(): void
    {
        $user = User::query()->create([
            'name' => 'Tenant Admin',
            'email' => 'tenant@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $company = Company::query()->create([
            'name' => 'Tenant Co',
            'email' => 'tenant@example.com',
            'is_active' => true,
            'status' => Company::STATUS_ACTIVE,
        ]);

        $membership = Membership::query()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'administrator',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $token = app(IssueApiToken::class)->handle($user, $membership, 'tenant-admin')['plain_text_token'];

        $this->withToken($token)
            ->getJson('/api/v1/admin/companies')
            ->assertNotFound();

        $this->withToken($token)
            ->postJson('/api/v1/admin/companies', [
                'name' => 'X',
                'status' => Company::STATUS_ACTIVE,
                'is_active' => true,
                'passwordless_code_enabled' => true,
                'magic_link_enabled' => true,
            ])
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/v1/admin/overview')
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/v1/admin/alerts')
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/v1/admin/audits')
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/v1/admin/billing/invoices')
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/v1/admin/plans')
            ->assertNotFound();

        $this->withToken($token)
            ->postJson('/api/v1/admin/plans', [
                'slug' => 'x',
                'name' => 'X',
            ])
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/v1/admin/companies/'.$company->getKey().'/audits')
            ->assertNotFound();
    }

    public function test_delete_requires_exact_confirmation(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => Hash::make('secret-pass'),
            'super_admin' => true,
        ]);

        $token = app(IssueApiToken::class)->handle($superAdmin, null, 'admin-test')['plain_text_token'];

        $company = Company::query()->create([
            'name' => 'Empresa Alvo',
            'email' => 'alvo@example.com',
            'is_active' => true,
            'status' => Company::STATUS_ACTIVE,
        ]);

        $this->withToken($token)
            ->deleteJson('/api/v1/admin/companies/'.$company->getKey(), [
                'confirmation' => 'valor errado',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The confirmation value must exactly match the company name.');
    }

    public function test_super_admin_can_list_global_admin_audits_across_companies(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => Hash::make('secret-pass'),
            'super_admin' => true,
        ]);

        $token = app(IssueApiToken::class)->handle($superAdmin, null, 'admin-audits')['plain_text_token'];

        $companyA = Company::query()->create([
            'name' => 'Empresa A',
            'email' => 'a@example.com',
            'is_active' => true,
            'status' => Company::STATUS_ACTIVE,
        ]);

        $companyB = Company::query()->create([
            'name' => 'Empresa B',
            'email' => 'b@example.com',
            'is_active' => true,
            'status' => Company::STATUS_ACTIVE,
        ]);

        $this->withToken($token)
            ->patchJson('/api/v1/admin/companies/'.$companyA->getKey().'/suspend')
            ->assertOk();

        $this->withToken($token)
            ->patchJson('/api/v1/admin/companies/'.$companyB->getKey().'/suspend')
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/admin/audits')
            ->assertOk()
            ->assertJsonFragment([
                'company_id' => $companyA->getKey(),
                'company_name' => 'Empresa A',
                'event_type' => 'company.suspended',
                'actor_type' => 'super_admin',
            ])
            ->assertJsonFragment([
                'company_id' => $companyB->getKey(),
                'company_name' => 'Empresa B',
                'event_type' => 'company.suspended',
                'actor_type' => 'super_admin',
            ]);
    }

    public function test_super_admin_can_list_global_subscriptions_with_filters(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => Hash::make('secret-pass'),
            'super_admin' => true,
        ]);

        $token = app(IssueApiToken::class)->handle($superAdmin, null, 'admin-subscriptions')['plain_text_token'];

        $starter = Plan::query()->create([
            'slug' => 'starter-subscriptions-test',
            'name' => 'Starter Subscriptions Test',
            'max_users' => 3,
            'max_service_orders_per_month' => 50,
            'max_attachments_storage_mb' => 512,
            'reference_price_cents' => 9900,
        ]);

        $pro = Plan::query()->create([
            'slug' => 'pro-subscriptions-test',
            'name' => 'Pro Subscriptions Test',
            'max_users' => 10,
            'max_service_orders_per_month' => 250,
            'max_attachments_storage_mb' => 2048,
            'reference_price_cents' => 19900,
        ]);

        $expiringCompany = Company::query()->create([
            'name' => 'Empresa Vencendo',
            'email' => 'expiring@example.com',
            'is_active' => true,
            'status' => Company::STATUS_ACTIVE,
        ]);

        $stableCompany = Company::query()->create([
            'name' => 'Empresa Estavel',
            'email' => 'stable@example.com',
            'is_active' => true,
            'status' => Company::STATUS_ACTIVE,
        ]);

        $expiringSubscription = Subscription::query()->create([
            'company_id' => $expiringCompany->getKey(),
            'plan_id' => $starter->getKey(),
            'started_at' => now()->subDays(10),
            'expires_at' => now()->addDays(5),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        Subscription::query()->create([
            'company_id' => $stableCompany->getKey(),
            'plan_id' => $pro->getKey(),
            'started_at' => now()->subDays(15),
            'expires_at' => now()->addDays(30),
            'status' => Subscription::STATUS_SUSPENDED,
        ]);

        BillingInvoice::query()->create([
            'company_id' => $expiringCompany->getKey(),
            'subscription_id' => $expiringSubscription->getKey(),
            'gateway_invoice_id' => 'inv_test_1',
            'status' => 'pending',
            'amount_cents' => 9900,
            'currency' => 'BRL',
            'due_at' => now()->addDays(3),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/admin/subscriptions')
            ->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.active', 1)
            ->assertJsonPath('summary.suspended', 1)
            ->assertJsonPath('summary.expiring_next_15_days', 1)
            ->assertJsonFragment([
                'name' => 'Empresa Vencendo',
                'status' => 'pending',
            ]);

        $this->withToken($token)
            ->getJson('/api/v1/admin/subscriptions?expiring=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company.name', 'Empresa Vencendo');

        $this->withToken($token)
            ->getJson('/api/v1/admin/subscriptions?plan_id='.$starter->getKey())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company.name', 'Empresa Vencendo');
    }

    public function test_super_admin_can_load_admin_overview(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => Hash::make('secret-pass'),
            'super_admin' => true,
        ]);

        $token = app(IssueApiToken::class)->handle($superAdmin, null, 'admin-overview')['plain_text_token'];

        $starter = Plan::query()->create([
            'slug' => 'starter-overview-test',
            'name' => 'Starter Overview Test',
            'max_users' => 3,
            'max_service_orders_per_month' => 50,
            'max_attachments_storage_mb' => 512,
            'reference_price_cents' => 9900,
        ]);

        $activeCompany = Company::query()->create([
            'name' => 'Empresa Ativa Resumo',
            'email' => 'ativa-resumo@example.com',
            'is_active' => true,
            'status' => Company::STATUS_ACTIVE,
        ]);

        $suspendedCompany = Company::query()->create([
            'name' => 'Empresa Suspensa Resumo',
            'email' => 'suspensa-resumo@example.com',
            'is_active' => false,
            'status' => Company::STATUS_SUSPENDED,
        ]);

        Subscription::query()->create([
            'company_id' => $activeCompany->getKey(),
            'plan_id' => $starter->getKey(),
            'started_at' => now()->subDays(3),
            'expires_at' => now()->addDays(7),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        Subscription::query()->create([
            'company_id' => $suspendedCompany->getKey(),
            'plan_id' => $starter->getKey(),
            'started_at' => now()->subDays(4),
            'expires_at' => now()->addDays(20),
            'status' => Subscription::STATUS_SUSPENDED,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/admin/overview')
            ->assertOk()
            ->assertJsonPath('summary.total_plans', Plan::query()->count())
            ->assertJsonPath('summary.active_subscriptions', 1)
            ->assertJsonPath('summary.suspended_subscriptions', 1)
            ->assertJsonFragment([
                'name' => 'Empresa Ativa Resumo',
            ])
            ->assertJsonFragment([
                'name' => 'Empresa Suspensa Resumo',
            ]);
    }

    public function test_super_admin_can_list_global_billing_invoices(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => Hash::make('secret-pass'),
            'super_admin' => true,
        ]);

        $token = app(IssueApiToken::class)->handle($superAdmin, null, 'admin-billing-invoices')['plain_text_token'];

        $plan = Plan::query()->create([
            'slug' => 'billing-invoices-test',
            'name' => 'Billing Invoices Test',
            'max_users' => 5,
            'max_service_orders_per_month' => 100,
            'max_attachments_storage_mb' => 1024,
            'reference_price_cents' => 14900,
        ]);

        $company = Company::query()->create([
            'name' => 'Empresa Cobranca',
            'email' => 'cobranca@example.com',
            'is_active' => true,
            'status' => Company::STATUS_ACTIVE,
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->getKey(),
            'plan_id' => $plan->getKey(),
            'started_at' => now()->subDays(10),
            'expires_at' => now()->addDays(20),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $pendingInvoice = BillingInvoice::query()->create([
            'company_id' => $company->getKey(),
            'subscription_id' => $subscription->getKey(),
            'gateway_invoice_id' => 'invoice_pending_test',
            'status' => 'pending',
            'amount_cents' => 14900,
            'currency' => 'BRL',
            'due_at' => now()->subDays(2),
            'invoice_url' => 'https://example.test/invoice',
        ]);

        BillingInvoice::query()->create([
            'company_id' => $company->getKey(),
            'subscription_id' => $subscription->getKey(),
            'gateway_invoice_id' => 'invoice_paid_test',
            'status' => 'received',
            'amount_cents' => 14900,
            'currency' => 'BRL',
            'due_at' => now()->subDays(10),
            'paid_at' => now()->subDays(1),
            'pdf_url' => 'https://example.test/receipt.pdf',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/admin/billing/invoices')
            ->assertOk()
            ->assertJsonPath('summary.pending', 1)
            ->assertJsonPath('summary.overdue', 1)
            ->assertJsonPath('summary.paid', 1)
            ->assertJsonFragment([
                'name' => 'Empresa Cobranca',
                'status' => 'pending',
            ]);

        $this->withToken($token)
            ->getJson('/api/v1/admin/billing/invoices?overdue=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');

        $this->withToken($token)
            ->getJson('/api/v1/admin/billing/invoices/'.$pendingInvoice->getKey())
            ->assertOk()
            ->assertJsonPath('data.gateway_invoice_id', 'invoice_pending_test')
            ->assertJsonPath('data.company.name', 'Empresa Cobranca')
            ->assertJsonPath('data.subscription.plan.name', 'Billing Invoices Test');
    }

    public function test_super_admin_can_load_alerts(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => Hash::make('secret-pass'),
            'super_admin' => true,
        ]);

        $token = app(IssueApiToken::class)->handle($superAdmin, null, 'admin-alerts')['plain_text_token'];

        $plan = Plan::query()->create([
            'slug' => 'alerts-test',
            'name' => 'Alerts Test',
            'max_users' => 5,
            'max_service_orders_per_month' => 100,
            'max_attachments_storage_mb' => 1024,
            'reference_price_cents' => 14900,
        ]);

        $activeCompany = Company::query()->create([
            'name' => 'Empresa Alerta Vencimento',
            'email' => 'alerta-vencimento@example.com',
            'is_active' => true,
            'status' => Company::STATUS_ACTIVE,
        ]);

        $suspendedCompany = Company::query()->create([
            'name' => 'Empresa Alerta Suspensa',
            'email' => 'alerta-suspensa@example.com',
            'is_active' => false,
            'status' => Company::STATUS_SUSPENDED,
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $activeCompany->getKey(),
            'plan_id' => $plan->getKey(),
            'started_at' => now()->subDays(5),
            'expires_at' => now()->addDays(4),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        Subscription::query()->create([
            'company_id' => $suspendedCompany->getKey(),
            'plan_id' => $plan->getKey(),
            'started_at' => now()->subDays(8),
            'expires_at' => now()->addDays(20),
            'status' => Subscription::STATUS_SUSPENDED,
        ]);

        BillingInvoice::query()->create([
            'company_id' => $activeCompany->getKey(),
            'subscription_id' => $subscription->getKey(),
            'gateway_invoice_id' => 'invoice_alert_overdue',
            'status' => 'pending',
            'amount_cents' => 14900,
            'currency' => 'BRL',
            'due_at' => now()->subDays(1),
        ]);

        $this->withToken($token)
            ->patchJson('/api/v1/admin/companies/'.$suspendedCompany->getKey().'/suspend')
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/admin/alerts')
            ->assertOk()
            ->assertJsonPath('summary.overdue_invoices', 1)
            ->assertJsonPath('summary.expiring_subscriptions', 1)
            ->assertJsonPath('summary.suspended_companies', 1)
            ->assertJsonFragment([
                'name' => 'Empresa Alerta Vencimento',
            ])
            ->assertJsonFragment([
                'name' => 'Empresa Alerta Suspensa',
            ])
            ->assertJsonFragment([
                'event_type' => 'company.suspended',
            ]);
    }
}
