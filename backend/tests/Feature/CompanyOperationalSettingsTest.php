<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Companies\Models\CompanySettingAudit;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\Notifications\Jobs\SendCriticalServiceOrderNotificationJob;
use App\Domains\ServiceOrders\Models\IntakeChecklistTemplate;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CompanyOperationalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_settings_are_tenant_scoped_audited_and_applied_to_quotes_portal_and_notifications(): void
    {
        Queue::fake();

        [
            'manager_token' => $managerToken,
            'manager_membership_id' => $managerMembershipId,
            'technician_token' => $technicianToken,
            'service_order_id' => $serviceOrderId,
            'customer_id' => $customerId,
            'customer_contact_id' => $customerContactId,
            'equipment_id' => $equipmentId,
            'intake_checklist_template_id' => $intakeChecklistTemplateId,
        ] = $this->createServiceOrderScenario('alpha');

        [
            'manager_token' => $otherManagerToken,
        ] = $this->createServiceOrderScenario('beta');

        $this->withToken($managerToken)
            ->getJson('/api/v1/company/settings')
            ->assertOk()
            ->assertJsonPath('data.branding.brand_name', 'Acme alpha')
            ->assertJsonPath('data.branding.customer_contact_line', null)
            ->assertJsonPath('data.service_orders.reference_prefix', 'OS')
            ->assertJsonPath('data.portal.approved_text', 'Nossa equipe foi notificada e dará início ao serviço em breve.')
            ->assertJsonPath('data.portal.rejected_text', 'Entraremos em contato para mais informações.')
            ->assertJsonPath('data.quotes.default_validity_days', 7)
            ->assertJsonPath('data.finance.block_delivery_with_outstanding_balance', true)
            ->assertJsonPath('data.communications.notification_footer_text', null)
            ->assertJsonPath('data.notifications.quote_available', true);

        $this->withToken($managerToken)
            ->putJson('/api/v1/company/settings', [
                'brand_name' => 'Laboratorio Alpha',
                'customer_contact_line' => 'WhatsApp (11) 98888-0000',
                'service_order_reference_prefix' => 'lab',
                'portal_intro_text' => 'Revise aqui o status e aprove o orcamento desta ordem.',
                'portal_footer_text' => 'Nosso horario de atendimento e de segunda a sexta.',
                'portal_approved_text' => 'Recebemos sua aprovação e seguiremos com o reparo.',
                'portal_rejected_text' => 'Recebemos sua recusa e vamos orientar os próximos passos.',
                'default_quote_validity_days' => 5,
                'block_delivery_with_outstanding_balance' => false,
                'notify_quote_available' => false,
                'notification_footer_text' => 'Obrigado por confiar na Laboratorio Alpha.',
            ])
            ->assertOk()
            ->assertJsonPath('data.branding.brand_name', 'Laboratorio Alpha')
            ->assertJsonPath('data.branding.customer_contact_line', 'WhatsApp (11) 98888-0000')
            ->assertJsonPath('data.service_orders.reference_prefix', 'LAB')
            ->assertJsonPath('data.portal.intro_text', 'Revise aqui o status e aprove o orcamento desta ordem.')
            ->assertJsonPath('data.portal.footer_text', 'Nosso horario de atendimento e de segunda a sexta.')
            ->assertJsonPath('data.portal.approved_text', 'Recebemos sua aprovação e seguiremos com o reparo.')
            ->assertJsonPath('data.portal.rejected_text', 'Recebemos sua recusa e vamos orientar os próximos passos.')
            ->assertJsonPath('data.quotes.default_validity_days', 5)
            ->assertJsonPath('data.finance.block_delivery_with_outstanding_balance', false)
            ->assertJsonPath('data.communications.notification_footer_text', 'Obrigado por confiar na Laboratorio Alpha.')
            ->assertJsonPath('data.notifications.quote_available', false);

        $this->assertDatabaseHas('company_setting_audits', [
            'actor_membership_id' => $managerMembershipId,
        ]);

        $audit = CompanySettingAudit::query()
            ->withoutGlobalScopes()
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            [
                'brand_name',
                'customer_contact_line',
                'service_order_reference_prefix',
                'portal_intro_text',
                'portal_footer_text',
                'portal_approved_text',
                'portal_rejected_text',
                'default_quote_validity_days',
                'block_delivery_with_outstanding_balance',
                'notify_quote_available',
                'notification_footer_text',
            ],
            $audit->changed_keys,
        );
        $this->assertSame(7, $audit->before_values['default_quote_validity_days']);
        $this->assertSame(5, $audit->after_values['default_quote_validity_days']);
        $this->assertTrue($audit->before_values['block_delivery_with_outstanding_balance']);
        $this->assertFalse($audit->after_values['block_delivery_with_outstanding_balance']);
        $this->assertTrue($audit->before_values['notify_quote_available']);
        $this->assertFalse($audit->after_values['notify_quote_available']);

        $this->withToken($otherManagerToken)
            ->getJson('/api/v1/company/settings')
            ->assertOk()
            ->assertJsonPath('data.branding.brand_name', 'Acme beta')
            ->assertJsonPath('data.branding.customer_contact_line', null)
            ->assertJsonPath('data.service_orders.reference_prefix', 'OS')
            ->assertJsonPath('data.quotes.default_validity_days', 7)
            ->assertJsonPath('data.finance.block_delivery_with_outstanding_balance', true)
            ->assertJsonPath('data.communications.notification_footer_text', null)
            ->assertJsonPath('data.notifications.quote_available', true);

        $secondDraftId = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customerId,
                'customer_contact_id' => $customerContactId,
                'equipment_id' => $equipmentId,
                'intake_checklist_template_id' => $intakeChecklistTemplateId,
                'equipment_description' => 'Segundo equipamento de teste.',
                'reported_problem' => 'Precisa abrir com prefixo customizado.',
                'intake_checklist' => [
                    ['label' => 'Carregador entregue', 'checked' => true],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts/'.$secondDraftId.'/open')
            ->assertOk()
            ->assertJsonPath('data.reference', 'LAB-000002');

        $this->withToken($technicianToken)
            ->putJson('/api/v1/service-orders/'.$serviceOrderId.'/diagnosis', [
                'internal_notes' => 'Fonte e bateria precisam de substituicao.',
                'customer_summary' => 'Equipamento precisa de reparo na alimentacao.',
            ])
            ->assertOk();

        $quoteResponse = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes', [
                'notes' => 'Orcamento com validade configurada pela empresa.',
                'line_items' => [
                    [
                        'description' => 'Troca de fonte',
                        'quantity' => 1,
                        'unit_price_cents' => 35000,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.revision_number', 1);

        $this->assertSame(
            now()->addDays(5)->toDateString(),
            Carbon::parse($quoteResponse->json('data.valid_until'))->toDateString(),
        );

        $this->assertDatabaseMissing('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'quote_available',
        ]);

        Queue::assertNotPushed(SendCriticalServiceOrderNotificationJob::class);

        $portalToken = $quoteResponse->json('data.portal_token');

        $this->getJson('/api/v1/portal/quotes/'.$portalToken)
            ->assertOk()
            ->assertJsonPath('data.company.brand_name', 'Laboratorio Alpha')
            ->assertJsonPath('data.company.customer_contact_line', 'WhatsApp (11) 98888-0000')
            ->assertJsonPath('data.portal.intro_text', 'Revise aqui o status e aprove o orcamento desta ordem.')
            ->assertJsonPath('data.portal.footer_text', 'Nosso horario de atendimento e de segunda a sexta.')
            ->assertJsonPath('data.portal.approved_text', 'Recebemos sua aprovação e seguiremos com o reparo.')
            ->assertJsonPath('data.portal.rejected_text', 'Recebemos sua recusa e vamos orientar os próximos passos.');
    }

    /**
     * @return array{
     *     manager_token: string,
     *     manager_membership_id: int,
     *     technician_token: string,
     *     service_order_id: int,
     *     customer_id: int,
     *     customer_contact_id: int,
     *     equipment_id: int,
     *     intake_checklist_template_id: int
     * }
     */
    protected function createServiceOrderScenario(string $suffix): array
    {
        $company = Company::query()->create([
            'name' => 'Acme '.$suffix,
            'email' => $suffix.'.settings@example.com',
            'is_active' => true,
        ]);

        $managerUser = User::query()->create([
            'name' => 'Manager '.$suffix,
            'email' => 'manager.'.$suffix.'.settings@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $managerMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $managerUser->getKey(),
            'role' => 'manager',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $technicianUser = User::query()->create([
            'name' => 'Technician '.$suffix,
            'email' => 'technician.'.$suffix.'.settings@example.com',
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
            'name' => 'Cliente '.$suffix,
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato '.$suffix,
            'email' => 'cliente.'.$suffix.'.settings@example.com',
            'phone' => '(11) 97777-0000',
            'normalized_email' => 'cliente.'.$suffix.'.settings@example.com',
            'normalized_phone' => '11977770000',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Lenovo',
            'model' => 'ThinkPad',
            'serial_number' => 'SET-'.$suffix.'-'.$company->getKey(),
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
            'settings-manager-'.$suffix,
        )['plain_text_token'];

        $technicianToken = app(IssueApiToken::class)->handle(
            $technicianUser,
            $technicianMembership,
            'settings-technician-'.$suffix,
        )['plain_text_token'];

        $serviceOrderId = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'intake_checklist_template_id' => $template->getKey(),
                'equipment_description' => 'Notebook Lenovo ThinkPad.',
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
            'manager_token' => $managerToken,
            'manager_membership_id' => $managerMembership->getKey(),
            'technician_token' => $technicianToken,
            'service_order_id' => $serviceOrderId,
            'customer_id' => $customer->getKey(),
            'customer_contact_id' => $contact->getKey(),
            'equipment_id' => $equipment->getKey(),
            'intake_checklist_template_id' => $template->getKey(),
        ];
    }
}
