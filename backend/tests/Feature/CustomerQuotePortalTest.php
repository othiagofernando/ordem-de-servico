<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\ServiceOrders\Models\IntakeChecklistTemplate;
use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerQuotePortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_access_exactly_one_service_order_and_current_quote_through_token(): void
    {
        [$portalToken, , $serviceOrder] = $this->createPortalQuoteScenario();

        $this->getJson('/api/v1/portal/quotes/'.$portalToken)
            ->assertOk()
            ->assertJsonPath('data.service_order.id', $serviceOrder->getKey())
            ->assertJsonPath('data.service_order.reference', $serviceOrder->reference)
            ->assertJsonPath('data.quote.status', QuoteRevision::STATUS_PENDING)
            ->assertJsonPath('data.quote.revision_number', 1)
            ->assertJsonPath('data.quote.total_cents', 50000);
    }

    public function test_invalid_or_expired_portal_tokens_are_rejected_and_regeneration_invalidates_old_token(): void
    {
        [$portalToken, $attendantToken, $serviceOrder, $quoteId] = $this->createPortalQuoteScenario();

        $this->getJson('/api/v1/portal/quotes/invalid-token')
            ->assertNotFound();

        $this->travel(3)->days();

        $this->getJson('/api/v1/portal/quotes/'.$portalToken)
            ->assertNotFound();

        $this->travelBack();

        $newToken = $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrder->getKey().'/quotes/'.$quoteId.'/portal-token')
            ->assertOk()
            ->assertJsonPath('data.quote_revision_id', $quoteId)
            ->json('data.portal_token');

        $this->getJson('/api/v1/portal/quotes/'.$portalToken)
            ->assertNotFound();

        $this->getJson('/api/v1/portal/quotes/'.$newToken)
            ->assertOk()
            ->assertJsonPath('data.quote.id', $quoteId);
    }

    public function test_customer_decision_is_idempotent_and_captures_evidence(): void
    {
        [$portalToken, , $serviceOrder, $quoteId] = $this->createPortalQuoteScenario();

        $approve = $this->withHeaders([
            'Idempotency-Key' => 'customer-approve-1',
            'User-Agent' => 'PortalTest/1.0',
        ])->postJson('/api/v1/portal/quotes/'.$portalToken.'/decision', [
            'decision' => 'approve',
        ]);

        $approve->assertOk()
            ->assertJsonPath('data.quote.status', QuoteRevision::STATUS_APPROVED)
            ->assertJsonPath('data.quote.id', $quoteId);

        $this->withHeaders([
            'Idempotency-Key' => 'customer-approve-1',
            'User-Agent' => 'PortalTest/1.0',
        ])->postJson('/api/v1/portal/quotes/'.$portalToken.'/decision', [
            'decision' => 'approve',
        ])->assertOk()
            ->assertJsonPath('data.quote.status', QuoteRevision::STATUS_APPROVED);

        $this->postJson('/api/v1/portal/quotes/'.$portalToken.'/decision', [
            'decision' => 'reject',
        ])->assertConflict()
            ->assertJsonPath('message', 'This quote already has a final customer decision.');

        $this->assertDatabaseHas('quote_revisions', [
            'id' => $quoteId,
            'status' => QuoteRevision::STATUS_APPROVED,
            'decision_ip' => '127.0.0.1',
            'decision_user_agent' => 'PortalTest/1.0',
            'decision_idempotency_key' => 'customer-approve-1',
        ]);

        $this->assertDatabaseHas('service_order_timeline_events', [
            'service_order_id' => $serviceOrder->getKey(),
            'type' => 'quote_approved',
            'visibility' => 'customer',
        ]);
    }

    /**
     * @return array{string, string, ServiceOrder, int}
     */
    protected function createPortalQuoteScenario(): array
    {
        $company = Company::query()->create([
            'name' => 'Acme Assistencia',
            'email' => 'portal@example.com',
            'is_active' => true,
        ]);

        $attendantUser = User::query()->create([
            'name' => 'Attendant Portal',
            'email' => 'attendant.portal@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $attendantMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $attendantUser->getKey(),
            'role' => 'attendant',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $technicianUser = User::query()->create([
            'name' => 'Technician Portal',
            'email' => 'technician.portal@example.com',
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
            'name' => 'Cliente Portal',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Portal',
            'email' => 'cliente.portal@example.com',
            'phone' => '(11) 94444-3333',
            'normalized_email' => 'cliente.portal@example.com',
            'normalized_phone' => '11944443333',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Dell',
            'model' => 'Latitude 5420',
            'serial_number' => 'PORTAL-001-'.$company->getKey(),
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

        $attendantToken = app(IssueApiToken::class)->handle(
            $attendantUser,
            $attendantMembership,
            'portal-attendant-test',
        )['plain_text_token'];

        $technicianToken = app(IssueApiToken::class)->handle(
            $technicianUser,
            $technicianMembership,
            'portal-technician-test',
        )['plain_text_token'];

        $serviceOrderId = $this->withToken($attendantToken)
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

        $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/drafts/'.$serviceOrderId.'/open')
            ->assertOk();

        ServiceOrder::query()->findOrFail($serviceOrderId)
            ->forceFill([
                'assigned_membership_id' => $technicianMembership->getKey(),
            ])->save();

        $this->withToken($technicianToken)
            ->putJson('/api/v1/service-orders/'.$serviceOrderId.'/diagnosis', [
                'internal_notes' => 'Fonte precisa de troca.',
                'customer_summary' => 'Equipamento precisa de reparo na alimentacao.',
            ])->assertOk();

        $quote = $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes', [
                'valid_until' => now()->addDay()->toIso8601String(),
                'notes' => 'Primeiro orcamento.',
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

        $serviceOrder = ServiceOrder::query()->findOrFail($serviceOrderId);

        return [
            $quote->json('data.portal_token'),
            $attendantToken,
            $serviceOrder,
            $quote->json('data.id'),
        ];
    }
}
