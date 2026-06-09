<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\ServiceOrders\Models\IntakeChecklistTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServiceOrderOperationalDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_print_current_quote_document(): void
    {
        ['manager_token' => $managerToken, 'service_order_id' => $serviceOrderId] = $this->createPrintableDocumentsScenario();

        $this->withToken($managerToken)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId.'/documents/quote')
            ->assertOk()
            ->assertJsonPath('data.document_type', 'quote')
            ->assertJsonPath('data.company.name', 'Docs Test Company')
            ->assertJsonPath('data.service_order.reference', 'OS-000001')
            ->assertJsonPath('data.customer.name', 'Cliente Documentos')
            ->assertJsonPath('data.quote.revision_number', 1)
            ->assertJsonPath('data.quote.total_cents', 42000)
            ->assertJsonPath('data.quote.line_items.0.description', 'Troca de tela');
    }

    public function test_finance_user_can_print_payment_receipt_from_immutable_snapshot(): void
    {
        [
            'finance_token' => $financeToken,
            'service_order_id' => $serviceOrderId,
            'first_payment_id' => $paymentId,
            'customer_id' => $customerId,
        ] = $this->createPrintableDocumentsScenario();

        Customer::query()->findOrFail($customerId)
            ->forceFill(['name' => 'Cliente Alterado Depois do Pagamento'])
            ->save();

        $this->withToken($financeToken)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId.'/documents/payments/'.$paymentId.'/receipt')
            ->assertOk()
            ->assertJsonPath('data.document_type', 'receipt')
            ->assertJsonPath('data.receipt.payment_id', $paymentId)
            ->assertJsonPath('data.customer.name', 'Cliente Documentos')
            ->assertJsonPath('data.receipt.amount_cents', 20000)
            ->assertJsonPath('data.receipt.balance_after_payment_cents', 22000);
    }

    public function test_printable_delivery_term_uses_structured_handoff_data(): void
    {
        ['manager_token' => $managerToken, 'service_order_id' => $serviceOrderId] = $this->createPrintableDocumentsScenario();

        $this->withToken($managerToken)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId.'/documents/delivery-term')
            ->assertOk()
            ->assertJsonPath('data.document_type', 'delivery_term')
            ->assertJsonPath('data.service_order.received_by_name', 'Maria Cliente')
            ->assertJsonPath('data.service_order.delivery_notes', 'Entregue com capa e carregador.')
            ->assertJsonPath('data.service_order.outstanding_balance_cents', 0);
    }

    public function test_delivery_term_requires_delivered_service_order(): void
    {
        [
            'manager_token' => $managerToken,
            'service_order_id' => $serviceOrderId,
        ] = $this->createPrintableDocumentsScenario(deliverServiceOrder: false);

        $this->withToken($managerToken)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId.'/documents/delivery-term')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Only delivered service orders can expose a printable delivery term.');
    }

    /**
     * @return array{
     *   manager_token: string,
     *   finance_token: string,
     *   service_order_id: int,
     *   first_payment_id: int,
     *   customer_id: int
     * }
     */
    protected function createPrintableDocumentsScenario(bool $deliverServiceOrder = true): array
    {
        $company = Company::query()->create([
            'name' => 'Docs Test Company',
            'document' => '12.345.678/0001-90',
            'email' => 'docs@example.com',
            'phone' => '(11) 99999-1111',
            'is_active' => true,
        ]);

        $managerUser = User::query()->create([
            'name' => 'Manager Docs',
            'email' => 'manager.docs@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $managerMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $managerUser->getKey(),
            'role' => 'manager',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $financeUser = User::query()->create([
            'name' => 'Finance Docs',
            'email' => 'finance.docs@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $financeMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $financeUser->getKey(),
            'role' => 'finance',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $technicianUser = User::query()->create([
            'name' => 'Technician Docs',
            'email' => 'technician.docs@example.com',
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
            'name' => 'Cliente Documentos',
            'document' => '123.456.789-11',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Ana Cliente',
            'email' => 'cliente.docs@example.com',
            'phone' => '(11) 97777-0000',
            'normalized_email' => 'cliente.docs@example.com',
            'normalized_phone' => '11977770000',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Smartphone',
            'brand' => 'Samsung',
            'model' => 'Galaxy S24',
            'serial_number' => 'DOCS-'.$company->getKey(),
        ]);

        $template = IntakeChecklistTemplate::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'category' => 'Smartphone',
            'version' => 1,
            'title' => 'Checklist Docs',
            'items' => [
                ['key' => 'screen', 'label' => 'Tela intacta', 'type' => 'boolean', 'required' => true],
            ],
        ]);

        $managerToken = app(IssueApiToken::class)->handle($managerUser, $managerMembership, 'docs-manager-test')['plain_text_token'];
        $financeToken = app(IssueApiToken::class)->handle($financeUser, $financeMembership, 'docs-finance-test')['plain_text_token'];
        $technicianToken = app(IssueApiToken::class)->handle($technicianUser, $technicianMembership, 'docs-technician-test')['plain_text_token'];

        $draftId = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'intake_checklist_template_id' => $template->getKey(),
                'equipment_description' => 'Galaxy S24 azul.',
                'reported_problem' => 'Tela quebrada.',
                'intake_checklist' => [
                    ['label' => 'Tela intacta', 'checked' => false, 'notes' => 'Chegou trincada'],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $serviceOrderId = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts/'.$draftId.'/open')
            ->assertOk()
            ->json('data.id');

        \App\Domains\ServiceOrders\Models\ServiceOrder::query()->findOrFail($serviceOrderId)
            ->forceFill([
                'assigned_membership_id' => $technicianMembership->getKey(),
            ])
            ->save();

        $this->withToken($technicianToken)
            ->putJson('/api/v1/service-orders/'.$serviceOrderId.'/diagnosis', [
                'internal_notes' => 'Substituicao de conjunto frontal.',
                'customer_summary' => 'Equipamento precisa de troca de tela.',
            ])
            ->assertOk();

        $quote = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes', [
                'valid_until' => now()->addDays(7)->toIso8601String(),
                'notes' => 'Prazo de 2 dias uteis.',
                'line_items' => [
                    [
                        'description' => 'Troca de tela',
                        'quantity' => 1,
                        'unit_price_cents' => 32000,
                    ],
                    [
                        'description' => 'Mao de obra',
                        'quantity' => 1,
                        'unit_price_cents' => 10000,
                    ],
                ],
            ])
            ->assertCreated();

        $this->postJson('/api/v1/portal/quotes/'.$quote->json('data.portal_token').'/decision', [
            'decision' => 'approve',
        ])->assertOk();

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/charges/derive-approved-quote')
            ->assertCreated();

        $firstPaymentId = $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/payments', [
                'amount_cents' => 20000,
                'payment_method' => 'pix',
                'notes' => 'Entrada inicial.',
            ])
            ->assertCreated()
            ->json('data.payments.0.id');

        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/start')
            ->assertOk();

        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/complete', [
                'final_test_results' => [
                    ['label' => 'Touch funcionando', 'passed' => true],
                ],
            ])
            ->assertOk();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/ready-for-pickup')
            ->assertOk();

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/payments', [
                'amount_cents' => 22000,
                'payment_method' => 'cash',
                'notes' => 'Quitacao no balcão.',
            ])
            ->assertCreated();

        if ($deliverServiceOrder) {
            $this->withToken($managerToken)
                ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/delivery', [
                    'received_by_name' => 'Maria Cliente',
                    'notes' => 'Entregue com capa e carregador.',
                ])
                ->assertOk();
        }

        return [
            'manager_token' => $managerToken,
            'finance_token' => $financeToken,
            'service_order_id' => $serviceOrderId,
            'first_payment_id' => $firstPaymentId,
            'customer_id' => $customer->getKey(),
        ];
    }
}
