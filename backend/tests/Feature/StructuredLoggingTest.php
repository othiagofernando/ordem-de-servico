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
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StructuredLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected string $structuredLogPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->structuredLogPath = storage_path('logs/structured.log');

        if (file_exists($this->structuredLogPath)) {
            unlink($this->structuredLogPath);
        }
    }

    public function test_authenticated_request_and_queued_notification_job_share_structured_context(): void
    {
        Mail::fake();

        [$managerToken, $serviceOrderId] = $this->createDiagnosedOrderScenario();
        $response = $this->withToken($managerToken)
            ->withHeaders([
                'X-Correlation-Id' => 'corr-quote-store-123',
            ])
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/quotes', [
                'valid_until' => now()->addDay()->toIso8601String(),
                'notes' => 'Orcamento com log estruturado.',
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

        $correlationId = $response->headers->get('X-Correlation-Id');
        $quoteId = $response->json('data.id');
        $deliveryId = $this->app['db']->table('notification_deliveries')->value('id');

        $requestLog = $this->findStructuredLog('api.request.completed', [
            'route_name' => 'api.v1.service-orders.quotes.store',
            'service_order_id' => $serviceOrderId,
        ]);
        $jobLog = $this->findStructuredLog('notification.job.sent', [
            'event_type' => 'quote_available',
            'service_order_id' => $serviceOrderId,
        ]);

        $this->assertNotNull($correlationId);
        $this->assertSame($correlationId, $requestLog['context']['correlation_id']);
        $this->assertSame('api.v1.service-orders.quotes.store', $requestLog['context']['route_name']);
        $this->assertSame($serviceOrderId, $requestLog['context']['service_order_id']);
        $this->assertSame(201, $requestLog['context']['response_status']);
        $this->assertArrayHasKey('company_id', $requestLog['context']);
        $this->assertArrayHasKey('actor_membership_id', $requestLog['context']);
        $this->assertArrayHasKey('actor_user_id', $requestLog['context']);

        $this->assertSame($correlationId, $jobLog['context']['correlation_id']);
        $this->assertSame($serviceOrderId, $jobLog['context']['service_order_id']);
        $this->assertSame($quoteId, $jobLog['context']['quote_revision_id']);
        $this->assertSame($deliveryId, $jobLog['context']['notification_delivery_id']);
        $this->assertSame('quote_available', $jobLog['context']['event_type']);
        $this->assertSame('sent', $jobLog['context']['status']);
        $this->assertArrayHasKey('company_id', $jobLog['context']);
        $this->assertArrayHasKey('actor_membership_id', $jobLog['context']);
        $this->assertArrayHasKey('actor_user_id', $jobLog['context']);
    }

    /**
     * @return array{string, int}
     */
    protected function createDiagnosedOrderScenario(): array
    {
        $suffix = (string) str()->uuid();

        $company = Company::query()->create([
            'name' => 'Acme Logs',
            'email' => 'logs-'.$suffix.'@example.com',
            'is_active' => true,
        ]);

        $managerUser = User::query()->create([
            'name' => 'Manager Logs',
            'email' => 'manager.logs.'.$suffix.'@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $managerMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $managerUser->getKey(),
            'role' => 'manager',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $technicianUser = User::query()->create([
            'name' => 'Technician Logs',
            'email' => 'technician.logs.'.$suffix.'@example.com',
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
            'name' => 'Cliente Logs',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Logs',
            'email' => 'cliente.logs@example.com',
            'phone' => '(11) 95555-3333',
            'normalized_email' => 'cliente.logs@example.com',
            'normalized_phone' => '11955553333',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Dell',
            'model' => 'Inspiron',
            'serial_number' => 'LOG-'.$company->getKey(),
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
            'logs-manager-test',
        )['plain_text_token'];

        $technicianToken = app(IssueApiToken::class)->handle(
            $technicianUser,
            $technicianMembership,
            'logs-technician-test',
        )['plain_text_token'];

        $serviceOrderId = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'intake_checklist_template_id' => $template->getKey(),
                'equipment_description' => 'Notebook Dell Inspiron.',
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

        $this->app['db']->table('service_orders')
            ->where('id', $serviceOrderId)
            ->update([
                'assigned_membership_id' => $technicianMembership->getKey(),
            ]);

        $this->withToken($technicianToken)
            ->putJson('/api/v1/service-orders/'.$serviceOrderId.'/diagnosis', [
                'internal_notes' => 'Fonte comprometida.',
                'customer_summary' => 'Equipamento precisa de reparo na alimentação.',
            ])
            ->assertOk();

        return [$managerToken, $serviceOrderId];
    }

    /**
     * @return array<string, mixed>
     */
    protected function findStructuredLog(string $message, array $expectedContext = []): array
    {
        $lines = file($this->structuredLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach (array_reverse($lines) as $line) {
            $record = json_decode($line, true);

            if (($record['message'] ?? null) !== $message) {
                continue;
            }

            $context = $record['context'] ?? [];

            $matches = collect($expectedContext)
                ->every(fn ($value, $key): bool => ($context[$key] ?? null) === $value);

            if ($matches) {
                return $record;
            }
        }

        $this->fail(sprintf('Structured log entry [%s] was not found.', $message));
    }
}
