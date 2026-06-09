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
            ->assertJsonPath('data.service_order.status', ServiceOrder::STATUS_OPEN)
            ->assertJsonPath('data.service_order.customer.name', 'Cliente Portal')
            ->assertJsonPath('data.service_order.equipment.brand', 'Dell')
            ->assertJsonPath('data.service_order.diagnosis_customer_summary', 'Equipamento precisa de reparo na alimentacao.')
            ->assertJsonPath('data.quote.status', QuoteRevision::STATUS_PENDING)
            ->assertJsonPath('data.quote.revision_number', 1)
            ->assertJsonPath('data.quote.total_cents', 50000)
            ->assertJsonCount(3, 'data.service_order.timeline');
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
            ->assertJsonPath('data.quote.id', $quoteId)
            ->assertJsonPath('data.service_order.timeline.3.type', 'quote_approved');

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

        $this->getJson('/api/v1/portal/quotes/'.$portalToken)
            ->assertOk()
            ->assertJsonPath('data.quote.status', QuoteRevision::STATUS_APPROVED)
            ->assertJsonPath('data.service_order.timeline.3.type', 'quote_approved');
    }

    public function test_portal_timeline_filters_internal_events_and_keeps_token_scoped_to_one_service_order(): void
    {
        [$portalToken, $attendantToken, $serviceOrder] = $this->createPortalQuoteScenario();

        $otherServiceOrderId = $this->createSecondPortalServiceOrder($attendantToken, $serviceOrder->company_id);

        $response = $this->getJson('/api/v1/portal/quotes/'.$portalToken)
            ->assertOk()
            ->assertJsonPath('data.service_order.id', $serviceOrder->getKey());

        $timeline = $response->json('data.service_order.timeline');

        $this->assertSame(
            ['service_order_opened', 'diagnosis_customer_summary_updated', 'quote_submitted'],
            collect($timeline)->pluck('type')->all(),
        );

        $this->assertNotSame($otherServiceOrderId, $response->json('data.service_order.id'));
    }

    public function test_portal_exposes_operational_documents_for_the_same_service_order_only(): void
    {
        [
            'portal_token' => $portalToken,
            'other_portal_token' => $otherPortalToken,
            'service_order_id' => $serviceOrderId,
            'payment_id' => $paymentId,
            'other_payment_id' => $otherPaymentId,
        ] = $this->createDeliveredPortalDocumentScenario();

        $portalResponse = $this->getJson('/api/v1/portal/quotes/'.$portalToken)
            ->assertOk()
            ->assertJsonPath('data.service_order.documents.quote.available', true)
            ->assertJsonPath('data.service_order.documents.receipts.0.payment_id', $paymentId)
            ->assertJsonPath('data.service_order.documents.delivery_term.available', true);

        $timeline = collect($portalResponse->json('data.service_order.timeline'))->pluck('type')->all();
        $this->assertContains('payment_received', $timeline);
        $this->assertContains('service_order_ready_for_pickup', $timeline);
        $this->assertContains('service_order_delivered', $timeline);

        $this->getJson('/api/v1/portal/quotes/'.$portalToken.'/documents/quote')
            ->assertOk()
            ->assertJsonPath('data.document_type', 'quote')
            ->assertJsonPath('data.service_order.id', $serviceOrderId);

        $this->getJson('/api/v1/portal/quotes/'.$portalToken.'/documents/payments/'.$paymentId.'/receipt')
            ->assertOk()
            ->assertJsonPath('data.document_type', 'receipt')
            ->assertJsonPath('data.receipt.payment_id', $paymentId);

        $this->getJson('/api/v1/portal/quotes/'.$portalToken.'/documents/delivery-term')
            ->assertOk()
            ->assertJsonPath('data.document_type', 'delivery_term')
            ->assertJsonPath('data.service_order.id', $serviceOrderId);

        $this->getJson('/api/v1/portal/quotes/'.$portalToken.'/documents/payments/'.$otherPaymentId.'/receipt')
            ->assertNotFound();

        $this->getJson('/api/v1/portal/quotes/'.$otherPortalToken.'/documents/payments/'.$paymentId.'/receipt')
            ->assertNotFound();
    }

    /**
     * @return array{string, string, ServiceOrder, int}
     */
    protected function createPortalQuoteScenario(): array
    {
        $suffix = (string) (Company::query()->count() + 1);

        $company = Company::query()->create([
            'name' => 'Acme Assistencia',
            'email' => 'portal'.$suffix.'@example.com',
            'is_active' => true,
        ]);

        $attendantUser = User::query()->create([
            'name' => 'Attendant Portal',
            'email' => 'attendant.portal'.$suffix.'@example.com',
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
            'email' => 'technician.portal'.$suffix.'@example.com',
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
            'email' => 'cliente.portal'.$suffix.'@example.com',
            'phone' => '(11) 94444-3333',
            'normalized_email' => 'cliente.portal'.$suffix.'@example.com',
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

    protected function createSecondPortalServiceOrder(string $attendantToken, int $companyId): int
    {
        $customer = Customer::query()->create([
            'company_id' => $companyId,
            'customer_type' => 'pf',
            'name' => 'Cliente Secundario',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $companyId,
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Secundario',
            'email' => 'cliente.secundario@example.com',
            'phone' => '(11) 97777-6666',
            'normalized_email' => 'cliente.secundario@example.com',
            'normalized_phone' => '11977776666',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $companyId,
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Samsung',
            'model' => 'Tab S9',
            'serial_number' => 'PORTAL-ALT-'.$companyId,
        ]);

        $template = IntakeChecklistTemplate::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->firstOrFail();

        $serviceOrderId = $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'intake_checklist_template_id' => $template->getKey(),
                'equipment_description' => 'Notebook Samsung Tab S9.',
                'reported_problem' => 'Tela sem imagem.',
                'intake_checklist' => [
                    ['label' => 'Carregador entregue', 'checked' => true],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/drafts/'.$serviceOrderId.'/open')
            ->assertOk();

        $technicianMembership = Membership::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('role', 'technician')
            ->firstOrFail();

        ServiceOrder::query()->findOrFail($serviceOrderId)
            ->forceFill([
                'assigned_membership_id' => $technicianMembership->getKey(),
            ])
            ->save();

        return $serviceOrderId;
    }

    /**
     * @return array{
     *   portal_token: string,
     *   other_portal_token: string,
     *   service_order_id: int,
     *   payment_id: int,
     *   other_payment_id: int
     * }
     */
    protected function createDeliveredPortalDocumentScenario(): array
    {
        [$portalToken, $attendantToken, $serviceOrder] = $this->createPortalQuoteScenario();

        $company = Company::query()->findOrFail($serviceOrder->company_id);

        $financeUser = User::query()->create([
            'name' => 'Finance Portal',
            'email' => 'finance.portal@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $financeMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $financeUser->getKey(),
            'role' => 'finance',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $financeToken = app(IssueApiToken::class)->handle(
            $financeUser,
            $financeMembership,
            'portal-finance-test',
        )['plain_text_token'];

        $this->postJson('/api/v1/portal/quotes/'.$portalToken.'/decision', [
            'decision' => 'approve',
        ])->assertOk();

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrder->getKey().'/financials/charges/derive-approved-quote')
            ->assertCreated();

        $paymentId = $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrder->getKey().'/financials/payments', [
                'amount_cents' => 20000,
                'payment_method' => 'pix',
                'notes' => 'Entrada do portal.',
            ])
            ->assertCreated()
            ->json('data.payments.0.id');

        $technicianMembershipId = ServiceOrder::query()->findOrFail($serviceOrder->getKey())->assigned_membership_id;
        $technicianMembership = Membership::query()->withoutGlobalScopes()->findOrFail($technicianMembershipId);
        $technicianToken = app(IssueApiToken::class)->handle(
            $technicianMembership->user,
            $technicianMembership,
            'portal-tech-repeat-test',
        )['plain_text_token'];

        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrder->getKey().'/execution/start')
            ->assertOk();

        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrder->getKey().'/execution/complete', [
                'final_test_results' => [
                    ['label' => 'Liga corretamente', 'passed' => true],
                ],
            ])
            ->assertOk();

        $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrder->getKey().'/ready-for-pickup')
            ->assertOk();

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrder->getKey().'/financials/payments', [
                'amount_cents' => 30000,
                'payment_method' => 'cash',
                'notes' => 'Quitacao final.',
            ])
            ->assertCreated();

        $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrder->getKey().'/delivery', [
                'received_by_name' => 'Cliente Portal',
                'notes' => 'Entrega registrada no portal.',
            ])
            ->assertOk();

        $otherServiceOrderId = $this->createSecondPortalServiceOrder($attendantToken, $serviceOrder->company_id);
        $technicianMembershipId = ServiceOrder::query()->findOrFail($otherServiceOrderId)->assigned_membership_id;
        $technicianMembership = Membership::query()->withoutGlobalScopes()->findOrFail($technicianMembershipId);
        $technicianToken = app(IssueApiToken::class)->handle(
            $technicianMembership->user,
            $technicianMembership,
            'portal-second-tech-test',
        )['plain_text_token'];

        $this->withToken($technicianToken)
            ->putJson('/api/v1/service-orders/'.$otherServiceOrderId.'/diagnosis', [
                'internal_notes' => 'Segundo equipamento precisa de reparo.',
                'customer_summary' => 'Segundo equipamento aguardando reparo.',
            ])->assertOk();

        $otherQuote = $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/'.$otherServiceOrderId.'/quotes', [
                'valid_until' => now()->addDay()->toIso8601String(),
                'notes' => 'Segundo orçamento.',
                'line_items' => [
                    [
                        'description' => 'Troca de conector',
                        'quantity' => 1,
                        'unit_price_cents' => 18000,
                    ],
                ],
            ])
            ->assertCreated();

        $otherPortalToken = $otherQuote->json('data.portal_token');

        $this->postJson('/api/v1/portal/quotes/'.$otherPortalToken.'/decision', [
            'decision' => 'approve',
        ])->assertOk();

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$otherServiceOrderId.'/financials/charges/derive-approved-quote')
            ->assertCreated();

        $otherPaymentId = $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$otherServiceOrderId.'/financials/payments', [
                'amount_cents' => 10000,
                'payment_method' => 'pix',
            ])
            ->assertCreated()
            ->json('data.payments.0.id');

        return [
            'portal_token' => $portalToken,
            'other_portal_token' => $otherPortalToken,
            'service_order_id' => $serviceOrder->getKey(),
            'payment_id' => $paymentId,
            'other_payment_id' => $otherPaymentId,
        ];
    }
}
