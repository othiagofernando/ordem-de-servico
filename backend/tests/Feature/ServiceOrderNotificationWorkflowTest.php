<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\Notifications\Mail\CriticalServiceOrderNotificationMail;
use App\Domains\Notifications\Jobs\SendCriticalServiceOrderNotificationJob;
use App\Domains\Notifications\Models\NotificationDelivery;
use App\Domains\ServiceOrders\Models\IntakeChecklistTemplate;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class ServiceOrderNotificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_critical_events_queue_notifications_and_persist_delivery_records(): void
    {
        Queue::fake();

        [$managerToken, $financeToken, $serviceOrderId, $portalToken, $technicianToken] = $this->createPendingQuoteScenario();

        $this->assertDatabaseHas('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'quote_available',
            'status' => NotificationDelivery::STATUS_QUEUED,
        ]);

        Queue::assertPushed(SendCriticalServiceOrderNotificationJob::class, 1);

        $this->postJson('/api/v1/portal/quotes/'.$portalToken.'/decision', [
            'decision' => 'approve',
        ])->assertOk();

        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/start')
            ->assertOk();

        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/complete', [
                'final_test_results' => [
                    [
                        'label' => 'Inicialização',
                        'passed' => true,
                    ],
                ],
            ])
            ->assertOk();

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/charges/derive-approved-quote')
            ->assertCreated();

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/payments', [
                'amount_cents' => 50000,
                'payment_method' => 'pix',
                'notes' => 'Pagamento com recibo.',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'payment_receipt',
            'status' => NotificationDelivery::STATUS_QUEUED,
        ]);

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/ready-for-pickup')
            ->assertOk();

        $this->assertDatabaseHas('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'ready_for_pickup',
            'status' => NotificationDelivery::STATUS_QUEUED,
        ]);

        Queue::assertPushed(SendCriticalServiceOrderNotificationJob::class, 4);
    }

    public function test_expiring_quote_notifications_are_dispatched_once_through_the_scheduled_command(): void
    {
        Queue::fake();

        [, , $serviceOrderId] = $this->createPendingQuoteScenario(validUntil: now()->addHours(12));

        Artisan::call('notifications:dispatch-expiring-quotes');

        $this->assertDatabaseHas('notification_deliveries', [
            'service_order_id' => $serviceOrderId,
            'event_type' => 'quote_expiring',
            'status' => NotificationDelivery::STATUS_QUEUED,
        ]);

        Queue::assertPushed(SendCriticalServiceOrderNotificationJob::class, 2);

        Artisan::call('notifications:dispatch-expiring-quotes');

        $this->assertSame(
            1,
            NotificationDelivery::query()->where('service_order_id', $serviceOrderId)->where('event_type', 'quote_expiring')->count(),
        );
    }

    public function test_notification_job_marks_successful_and_failed_delivery_attempts(): void
    {
        Mail::fake();

        [, , $serviceOrderId] = $this->createPendingQuoteScenario();

        $delivery = NotificationDelivery::query()
            ->where('service_order_id', $serviceOrderId)
            ->where('event_type', 'quote_available')
            ->firstOrFail();

        (new SendCriticalServiceOrderNotificationJob($delivery->getKey()))->handle();

        $delivery->refresh();

        $this->assertSame(NotificationDelivery::STATUS_SENT, $delivery->status);
        $this->assertNotNull($delivery->sent_at);

        Mail::assertSentCount(1);

        $failedDelivery = NotificationDelivery::query()->create([
            'company_id' => $delivery->company_id,
            'service_order_id' => $delivery->service_order_id,
            'event_type' => 'payment_receipt',
            'recipient_email' => 'cliente.notificacoes@example.com',
            'recipient_name' => 'Cliente Notificacoes',
            'subject' => 'Recibo de pagamento',
            'payload' => [
                'service_order_reference' => 'OS-000001',
            ],
            'status' => NotificationDelivery::STATUS_QUEUED,
            'queued_at' => now(),
            'mailable' => 'critical-service-order-notification',
        ]);

        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP offline'));

        (new SendCriticalServiceOrderNotificationJob($failedDelivery->getKey()))->handle();

        $failedDelivery->refresh();

        $this->assertSame(NotificationDelivery::STATUS_FAILED, $failedDelivery->status);
        $this->assertNotNull($failedDelivery->failed_at);
        $this->assertStringContainsString('SMTP offline', (string) $failedDelivery->error_message);
    }

    public function test_notification_payload_and_mail_view_reflect_external_branding_settings(): void
    {
        Mail::fake();

        [$managerToken, $financeToken, $serviceOrderId, $portalToken, $technicianToken] = $this->createPendingQuoteScenario();

        $this->withToken($managerToken)
            ->putJson('/api/v1/company/settings', [
                'brand_name' => 'Laboratorio Notificacoes',
                'customer_contact_line' => 'WhatsApp (11) 95555-0000',
                'notify_payment_receipt_text' => 'Seu pagamento foi identificado com sucesso.',
                'notification_footer_text' => 'Atendimento em horário comercial.',
            ])
            ->assertOk();

        $this->postJson('/api/v1/portal/quotes/'.$portalToken.'/decision', [
            'decision' => 'approve',
        ])->assertOk();

        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/start')
            ->assertOk();

        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/execution/complete', [
                'final_test_results' => [
                    [
                        'label' => 'Inicialização',
                        'passed' => true,
                    ],
                ],
            ])
            ->assertOk();

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/charges/derive-approved-quote')
            ->assertCreated();

        $this->withToken($financeToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/financials/payments', [
                'amount_cents' => 50000,
                'payment_method' => 'pix',
                'notes' => 'Pagamento com branding atualizado.',
            ])
            ->assertCreated();

        $delivery = NotificationDelivery::query()
            ->where('service_order_id', $serviceOrderId)
            ->where('event_type', 'payment_receipt')
            ->latest('id')
            ->firstOrFail();

        $payload = $delivery->payload?->getArrayCopy() ?? [];

        $this->assertSame('Laboratorio Notificacoes', $payload['brand_name'] ?? null);
        $this->assertSame('WhatsApp (11) 95555-0000', $payload['customer_contact_line'] ?? null);
        $this->assertSame('Seu pagamento foi identificado com sucesso.', $payload['notification_intro_text'] ?? null);
        $this->assertSame('Atendimento em horário comercial.', $payload['notification_footer_text'] ?? null);

        (new SendCriticalServiceOrderNotificationJob($delivery->getKey()))->handle();

        Mail::assertSent(CriticalServiceOrderNotificationMail::class, function (CriticalServiceOrderNotificationMail $mail): bool {
            $viewData = $mail->content()->with;

            return ($viewData['companyName'] ?? null) === 'Laboratorio Notificacoes'
                && (($viewData['payload']['customer_contact_line'] ?? null) === 'WhatsApp (11) 95555-0000')
                && (($viewData['payload']['notification_intro_text'] ?? null) === 'Seu pagamento foi identificado com sucesso.')
                && (($viewData['payload']['notification_footer_text'] ?? null) === 'Atendimento em horário comercial.');
        });
    }

    /**
     * @return array{string, string, int, string, string}
     */
    protected function createPendingQuoteScenario(?Carbon $validUntil = null): array
    {
        $suffix = (string) str()->uuid();

        $company = Company::query()->create([
            'name' => 'Acme Notificacoes',
            'email' => 'notifications-'.$suffix.'@example.com',
            'is_active' => true,
        ]);

        $managerUser = User::query()->create([
            'name' => 'Manager Notifications',
            'email' => 'manager.notifications.'.$suffix.'@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $managerMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $managerUser->getKey(),
            'role' => 'manager',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $financeUser = User::query()->create([
            'name' => 'Finance Notifications',
            'email' => 'finance.notifications.'.$suffix.'@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $financeMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $financeUser->getKey(),
            'role' => 'finance',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $technicianUser = User::query()->create([
            'name' => 'Technician Notifications',
            'email' => 'technician.notifications.'.$suffix.'@example.com',
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
            'name' => 'Cliente Notificacoes',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Notificacoes',
            'email' => 'cliente.notificacoes@example.com',
            'phone' => '(11) 98888-1111',
            'normalized_email' => 'cliente.notificacoes@example.com',
            'normalized_phone' => '11988881111',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Samsung',
            'model' => 'Book',
            'serial_number' => 'NOT-'.$company->getKey(),
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
            'notifications-manager-test',
        )['plain_text_token'];

        $financeToken = app(IssueApiToken::class)->handle(
            $financeUser,
            $financeMembership,
            'notifications-finance-test',
        )['plain_text_token'];

        $technicianToken = app(IssueApiToken::class)->handle(
            $technicianUser,
            $technicianMembership,
            'notifications-technician-test',
        )['plain_text_token'];

        $serviceOrderId = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'intake_checklist_template_id' => $template->getKey(),
                'equipment_description' => 'Notebook Samsung Book.',
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
            ->forceFill(['assigned_membership_id' => $technicianMembership->getKey()])
            ->save();

        $this->withToken($technicianToken)
            ->putJson('/api/v1/service-orders/'.$serviceOrderId.'/diagnosis', [
                'internal_notes' => 'Necessário reparo em alimentação.',
                'customer_summary' => 'Equipamento precisa de reparo na alimentação.',
            ])
            ->assertOk();

        $quote = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes', [
                'valid_until' => ($validUntil ?? now()->addDays(2))->toIso8601String(),
                'line_items' => [
                    [
                        'description' => 'Reparo em alimentação',
                        'quantity' => 1,
                        'unit_price_cents' => 50000,
                    ],
                ],
            ])
            ->assertCreated();

        return [
            $managerToken,
            $financeToken,
            $serviceOrderId,
            $quote->json('data.portal_token'),
            $technicianToken,
        ];
    }
}
