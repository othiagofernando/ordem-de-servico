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

class QuoteRevisionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_submission_is_blocked_until_diagnosis_is_completed(): void
    {
        [$attendantToken, , , , $serviceOrderId] = $this->createOpenedServiceOrderScenario();

        $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes', [
                'valid_until' => now()->addDays(7)->toIso8601String(),
                'line_items' => [
                    [
                        'description' => 'Troca de fonte',
                        'quantity' => 1,
                        'unit_price_cents' => 35000,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['diagnosis_completed_at']);
    }

    public function test_quote_revisions_require_at_most_one_pending_quote_and_allow_new_revision_after_expiry(): void
    {
        [$attendantToken, , $technicianToken, , $serviceOrderId] = $this->createOpenedServiceOrderScenario();

        $this->withToken($technicianToken)
            ->putJson('/api/v1/service-orders/'.$serviceOrderId.'/diagnosis', [
                'internal_notes' => 'Fonte e CI de carga precisam de substituicao.',
                'customer_summary' => 'Equipamento requer reparo na alimentacao.',
            ])
            ->assertOk();

        $firstQuote = $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes', [
                'valid_until' => now()->addDay()->toIso8601String(),
                'notes' => 'Primeira rodada de orcamento.',
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
            ->assertCreated()
            ->assertJsonPath('data.revision_number', 1)
            ->assertJsonPath('data.status', QuoteRevision::STATUS_PENDING)
            ->assertJsonPath('data.total_cents', 50000)
            ->assertJsonPath('data.decision_token_hash', null)
            ->assertJsonPath('data.decision_idempotency_key', null);

        $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes', [
                'valid_until' => now()->addDays(2)->toIso8601String(),
                'line_items' => [
                    [
                        'description' => 'Nova proposta',
                        'quantity' => 1,
                        'unit_price_cents' => 45000,
                    ],
                ],
            ])
            ->assertConflict()
            ->assertJsonPath('message', 'A pending quote already exists for this service order.');

        $this->travel(2)->days();

        $secondQuote = $this->withToken($attendantToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes', [
                'valid_until' => now()->addDays(3)->toIso8601String(),
                'notes' => 'Segunda rodada de orcamento.',
                'line_items' => [
                    [
                        'description' => 'Troca de fonte e CI',
                        'quantity' => 1,
                        'unit_price_cents' => 48000,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.revision_number', 2)
            ->assertJsonPath('data.status', QuoteRevision::STATUS_PENDING)
            ->assertJsonPath('data.total_cents', 48000);

        $this->withToken($attendantToken)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.revision_number', 1)
            ->assertJsonPath('data.0.status', QuoteRevision::STATUS_EXPIRED)
            ->assertJsonPath('data.1.revision_number', 2)
            ->assertJsonPath('data.1.status', QuoteRevision::STATUS_PENDING);

        $this->assertDatabaseHas('quote_revisions', [
            'id' => $firstQuote->json('data.id'),
            'status' => QuoteRevision::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseHas('quote_revisions', [
            'id' => $secondQuote->json('data.id'),
            'status' => QuoteRevision::STATUS_PENDING,
            'revision_number' => 2,
        ]);
    }

    /**
     * @return array{string, Membership, string, Membership, int}
     */
    protected function createOpenedServiceOrderScenario(): array
    {
        $company = Company::query()->create([
            'name' => 'Acme Assistencia',
            'email' => 'quotes@example.com',
            'is_active' => true,
        ]);

        $attendantUser = User::query()->create([
            'name' => 'Attendant Quotes',
            'email' => 'attendant.quotes@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $attendantMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $attendantUser->getKey(),
            'role' => 'attendant',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $technicianUser = User::query()->create([
            'name' => 'Technician Quotes',
            'email' => 'technician.quotes@example.com',
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
            'name' => 'Cliente Orcamento',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Orcamento',
            'email' => 'cliente.orcamento@example.com',
            'phone' => '(11) 95555-4444',
            'normalized_email' => 'cliente.orcamento@example.com',
            'normalized_phone' => '11955554444',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Dell',
            'model' => 'Latitude 5420',
            'serial_number' => 'QUOTE-001-'.$company->getKey(),
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
            'quote-attendant-test',
        )['plain_text_token'];

        $technicianToken = app(IssueApiToken::class)->handle(
            $technicianUser,
            $technicianMembership,
            'quote-technician-test',
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

        return [
            $attendantToken,
            $attendantMembership,
            $technicianToken,
            $technicianMembership,
            $serviceOrderId,
        ];
    }
}
