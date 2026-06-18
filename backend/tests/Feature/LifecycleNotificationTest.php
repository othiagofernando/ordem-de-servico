<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\Notifications\Jobs\SendCriticalServiceOrderNotificationJob;
use App\Domains\Notifications\Models\NotificationDelivery;
use App\Domains\ServiceOrders\Models\IntakeChecklistTemplate;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LifecycleNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_order_opened_notification_is_queued_when_setting_is_enabled(): void
    {
        Queue::fake();

        [, , $serviceOrderId] = $this->createOpenScenario(notifyOpened: true);

        $this->assertDatabaseHas('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'service_order_opened',
            'status' => NotificationDelivery::STATUS_QUEUED,
        ]);

        Queue::assertPushed(SendCriticalServiceOrderNotificationJob::class);
    }

    public function test_service_order_opened_notification_is_skipped_when_setting_is_disabled(): void
    {
        Queue::fake();

        [, , $serviceOrderId] = $this->createOpenScenario(notifyOpened: false);

        $this->assertDatabaseMissing('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'service_order_opened',
        ]);
    }

    public function test_service_order_in_progress_notification_is_queued_when_setting_is_enabled(): void
    {
        Queue::fake();

        [, $technicianToken, $serviceOrderId] = $this->createOpenScenario(notifyInProgress: true);

        $this->startExecution($technicianToken, $serviceOrderId);

        $this->assertDatabaseHas('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'service_order_in_progress',
            'status' => NotificationDelivery::STATUS_QUEUED,
        ]);
    }

    public function test_service_order_in_progress_notification_is_skipped_when_setting_is_disabled(): void
    {
        Queue::fake();

        [, $technicianToken, $serviceOrderId] = $this->createOpenScenario(notifyInProgress: false);

        $this->startExecution($technicianToken, $serviceOrderId);

        $this->assertDatabaseMissing('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'service_order_in_progress',
        ]);
    }

    public function test_service_order_completed_notification_is_queued_when_setting_is_enabled(): void
    {
        Queue::fake();

        [, $technicianToken, $serviceOrderId] = $this->createOpenScenario(notifyCompleted: true);

        $this->startExecution($technicianToken, $serviceOrderId);
        $this->completeExecution($technicianToken, $serviceOrderId);

        $this->assertDatabaseHas('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'service_order_completed',
            'status' => NotificationDelivery::STATUS_QUEUED,
        ]);
    }

    public function test_service_order_completed_notification_is_skipped_when_setting_is_disabled(): void
    {
        Queue::fake();

        [, $technicianToken, $serviceOrderId] = $this->createOpenScenario(notifyCompleted: false);

        $this->startExecution($technicianToken, $serviceOrderId);
        $this->completeExecution($technicianToken, $serviceOrderId);

        $this->assertDatabaseMissing('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'service_order_completed',
        ]);
    }

    public function test_service_order_delivered_notification_is_queued_when_setting_is_enabled(): void
    {
        Queue::fake();

        [$managerToken, $technicianToken, $serviceOrderId] = $this->createOpenScenario(notifyDelivered: true);

        $this->startExecution($technicianToken, $serviceOrderId);
        $this->completeExecution($technicianToken, $serviceOrderId);

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/ready-for-pickup')
            ->assertOk();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/charges/derive-approved-quote')
            ->assertCreated();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/delivery', [
                'received_by_name' => 'Cliente Teste',
                'allow_balance_override' => true,
                'balance_override_reason' => 'Entrega autorizada sem pagamento neste teste.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'service_order_delivered',
            'status' => NotificationDelivery::STATUS_QUEUED,
        ]);
    }

    public function test_service_order_delivered_notification_is_skipped_when_setting_is_disabled(): void
    {
        Queue::fake();

        [$managerToken, $technicianToken, $serviceOrderId] = $this->createOpenScenario(notifyDelivered: false);

        $this->startExecution($technicianToken, $serviceOrderId);
        $this->completeExecution($technicianToken, $serviceOrderId);

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/ready-for-pickup')
            ->assertOk();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/charges/derive-approved-quote')
            ->assertCreated();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/delivery', [
                'received_by_name' => 'Cliente Teste',
                'allow_balance_override' => true,
                'balance_override_reason' => 'Entrega autorizada sem pagamento neste teste.',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'service_order_delivered',
        ]);
    }

    public function test_service_order_cancelled_notification_is_queued_when_setting_is_enabled(): void
    {
        Queue::fake();

        [$managerToken, , $serviceOrderId] = $this->createOpenScenario(notifyCancelled: true);

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/close/cancel', [
                'notes' => 'Cancelado a pedido do cliente.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'service_order_cancelled',
            'status' => NotificationDelivery::STATUS_QUEUED,
        ]);
    }

    public function test_service_order_cancelled_notification_is_skipped_when_setting_is_disabled(): void
    {
        Queue::fake();

        [$managerToken, , $serviceOrderId] = $this->createOpenScenario(notifyCancelled: false);

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/close/cancel', [
                'notes' => 'Cancelado a pedido do cliente.',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'service_order_cancelled',
        ]);
    }

    public function test_service_order_no_repair_notification_is_queued_when_setting_is_enabled(): void
    {
        Queue::fake();

        [$managerToken, , $serviceOrderId] = $this->createOpenScenario(notifyNoRepair: true);

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/close/no-repair', [
                'notes' => 'Reparo inviável economicamente.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'service_order_no_repair',
            'status' => NotificationDelivery::STATUS_QUEUED,
        ]);
    }

    public function test_service_order_no_repair_notification_is_skipped_when_setting_is_disabled(): void
    {
        Queue::fake();

        [$managerToken, , $serviceOrderId] = $this->createOpenScenario(notifyNoRepair: false);

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/close/no-repair', [
                'notes' => 'Reparo inviável economicamente.',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'service_order_no_repair',
        ]);
    }

    public function test_notification_settings_are_returned_and_updated_via_company_settings_endpoint(): void
    {
        Queue::fake();

        [$managerToken] = $this->createOpenScenario();

        $response = $this->withToken($managerToken)
            ->getJson('/api/v1/company/settings')
            ->assertOk();

        $notifications = $response->json('data.notifications');

        $this->assertArrayHasKey('service_order_opened', $notifications);
        $this->assertArrayHasKey('service_order_in_progress', $notifications);
        $this->assertArrayHasKey('service_order_completed', $notifications);
        $this->assertArrayHasKey('service_order_delivered', $notifications);
        $this->assertArrayHasKey('service_order_cancelled', $notifications);
        $this->assertArrayHasKey('service_order_no_repair', $notifications);

        $this->withToken($managerToken)
            ->putJson('/api/v1/company/settings', [
                'notify_service_order_opened' => true,
                'notify_service_order_opened_text' => 'Sua OS foi aberta.',
                'notify_service_order_completed' => false,
                'notify_service_order_no_repair' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.notifications.service_order_opened', true)
            ->assertJsonPath('data.notifications.service_order_opened_text', 'Sua OS foi aberta.')
            ->assertJsonPath('data.notifications.service_order_completed', false)
            ->assertJsonPath('data.notifications.service_order_no_repair', false);
    }

    protected function startExecution(string $technicianToken, int $serviceOrderId): void
    {
        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/start')
            ->assertOk();
    }

    protected function completeExecution(string $technicianToken, int $serviceOrderId): void
    {
        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/complete', [
                'final_test_results' => [
                    [
                        'label' => 'Inicialização do sistema',
                        'passed' => true,
                    ],
                ],
            ])
            ->assertOk();
    }

    /**
     * Creates a company with configurable lifecycle notification settings and returns
     * [managerToken, technicianToken, serviceOrderId] with the OS already opened.
     *
     * @return array{string, string, int}
     */
    protected function createOpenScenario(
        bool $notifyOpened = false,
        bool $notifyInProgress = false,
        bool $notifyCompleted = true,
        bool $notifyDelivered = true,
        bool $notifyCancelled = false,
        bool $notifyNoRepair = true,
    ): array {
        $suffix = (string) str()->uuid();

        $company = Company::query()->create([
            'name' => 'Acme Lifecycle Notifications',
            'email' => 'lifecycle-'.$suffix.'@example.com',
            'is_active' => true,
        ]);

        $managerUser = User::query()->create([
            'name' => 'Manager Lifecycle',
            'email' => 'manager.lifecycle.'.$suffix.'@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $managerMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $managerUser->getKey(),
            'role' => 'manager',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $technicianUser = User::query()->create([
            'name' => 'Technician Lifecycle',
            'email' => 'technician.lifecycle.'.$suffix.'@example.com',
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
            'name' => 'Cliente Lifecycle',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Lifecycle',
            'email' => 'cliente.lifecycle.'.$suffix.'@example.com',
            'phone' => '(11) 97777-2222',
            'normalized_email' => 'cliente.lifecycle.'.$suffix.'@example.com',
            'normalized_phone' => '11977772222',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Dell',
            'model' => 'Latitude 5420',
            'serial_number' => 'LIFE-'.$company->getKey(),
        ]);

        $template = IntakeChecklistTemplate::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'category' => 'Notebook',
            'version' => 1,
            'title' => 'Checklist Lifecycle',
            'items' => [
                ['key' => 'charger', 'label' => 'Carregador entregue', 'type' => 'boolean', 'required' => true],
            ],
        ]);

        // Apply lifecycle notification settings directly on the model
        \App\Domains\Companies\Models\CompanyOperationalSetting::query()
            ->withoutGlobalScopes()
            ->firstOrNew(['company_id' => $company->getKey()])
            ->forceFill([
                'company_id' => $company->getKey(),
                'notify_service_order_opened' => $notifyOpened,
                'notify_service_order_in_progress' => $notifyInProgress,
                'notify_service_order_completed' => $notifyCompleted,
                'notify_service_order_delivered' => $notifyDelivered,
                'notify_service_order_cancelled' => $notifyCancelled,
                'notify_service_order_no_repair' => $notifyNoRepair,
            ])
            ->save();

        $managerToken = app(IssueApiToken::class)->handle(
            $managerUser,
            $managerMembership,
            'lifecycle-manager-test',
        )['plain_text_token'];

        $technicianToken = app(IssueApiToken::class)->handle(
            $technicianUser,
            $technicianMembership,
            'lifecycle-technician-test',
        )['plain_text_token'];

        $serviceOrderId = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'intake_checklist_template_id' => $template->getKey(),
                'equipment_description' => 'Notebook Dell Latitude 5420.',
                'reported_problem' => 'Não liga após queda.',
                'intake_checklist' => [
                    ['label' => 'Carregador entregue', 'checked' => true],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        // Assign technician before opening so execution can start
        ServiceOrder::query()->findOrFail($serviceOrderId)
            ->forceFill(['assigned_membership_id' => $technicianMembership->getKey()])
            ->save();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts/'.$serviceOrderId.'/open')
            ->assertOk();

        // For execution to start we need an approved quote
        $this->withToken($technicianToken)
            ->putJson('/api/v1/service-orders/'.$serviceOrderId.'/diagnosis', [
                'internal_notes' => 'Placa de vídeo danificada.',
                'customer_summary' => 'Equipamento com dano na placa de vídeo.',
            ])
            ->assertOk();

        $quote = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes', [
                'valid_until' => now()->addDays(2)->toIso8601String(),
                'line_items' => [
                    [
                        'description' => 'Reparo em placa de vídeo',
                        'quantity' => 1,
                        'unit_price_cents' => 35000,
                    ],
                ],
            ])
            ->assertCreated();

        $this->postJson('/api/v1/portal/quotes/'.$quote->json('data.portal_token').'/decision', [
            'decision' => 'approve',
        ])->assertOk();

        return [$managerToken, $technicianToken, $serviceOrderId];
    }
}
