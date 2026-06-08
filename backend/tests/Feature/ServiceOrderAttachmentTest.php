<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\ServiceOrders\Models\IntakeChecklistTemplate;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceOrderAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_upload_attachments_with_explicit_types(): void
    {
        Storage::fake('local');

        [$managerToken, $serviceOrderId] = $this->createOpenOrderScenario();

        foreach (ServiceOrderAttachment::ALLOWED_TYPES as $type) {
            $response = $this->withToken($managerToken)
                ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/attachments', [
                    'attachment_type' => $type,
                    'notes' => 'Nota sobre este anexo.',
                    'file' => UploadedFile::fake()->image('evidence.jpg'),
                ]);

            $response->assertCreated()
                ->assertJsonPath('data.attachment_type', $type)
                ->assertJsonPath('data.file_name', 'evidence.jpg')
                ->assertJsonFragment(['notes' => 'Nota sobre este anexo.']);
        }

        $this->withToken($managerToken)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId.'/attachments')
            ->assertOk()
            ->assertJsonCount(count(ServiceOrderAttachment::ALLOWED_TYPES), 'data');
    }

    public function test_other_type_is_accepted_as_fallback(): void
    {
        Storage::fake('local');

        [$managerToken, $serviceOrderId] = $this->createOpenOrderScenario();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/attachments', [
                'attachment_type' => 'other',
                'file' => UploadedFile::fake()->create('document.pdf', 512, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.attachment_type', 'other')
            ->assertJsonPath('data.file_name', 'document.pdf');
    }

    public function test_invalid_attachment_type_is_rejected(): void
    {
        [$managerToken, $serviceOrderId] = $this->createOpenOrderScenario();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/attachments', [
                'attachment_type' => 'unknown_type',
                'file' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attachment_type']);
    }

    public function test_storage_keys_are_tenant_scoped_and_non_guessable(): void
    {
        Storage::fake('local');

        [$managerToken, $serviceOrderId, $companyId] = $this->createOpenOrderScenario();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/attachments', [
                'attachment_type' => 'intake_photo',
                'file' => UploadedFile::fake()->image('entry.png'),
            ])
            ->assertCreated();

        $attachment = ServiceOrderAttachment::query()->withoutGlobalScopes()->first();

        $this->assertNotNull($attachment);
        $this->assertStringStartsWith($companyId.'/', $attachment->storage_key);
        $this->assertStringContainsString('/service-orders/'.$serviceOrderId.'/', $attachment->storage_key);
        Storage::disk('local')->assertExists($attachment->storage_key);
    }

    public function test_download_is_served_through_api_not_public_url(): void
    {
        Storage::fake('local');

        [$managerToken, $serviceOrderId] = $this->createOpenOrderScenario();

        $attachmentId = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/attachments', [
                'attachment_type' => 'diagnostic_image',
                'file' => UploadedFile::fake()->image('diag.jpg'),
            ])
            ->assertCreated()
            ->json('data.id');

        $downloadResponse = $this->withToken($managerToken)
            ->get('/api/v1/service-orders/'.$serviceOrderId.'/attachments/'.$attachmentId.'/download');

        $downloadResponse->assertOk();

        $listResponse = $this->withToken($managerToken)
            ->getJson('/api/v1/service-orders/'.$serviceOrderId.'/attachments');

        $listResponse->assertOk();
        foreach ($listResponse->json('data') as $item) {
            $this->assertArrayNotHasKey('url', $item, 'Attachments must not expose a direct public URL.');
            $this->assertArrayNotHasKey('storage_key', $item, 'Attachments must not expose raw storage keys.');
        }
    }

    public function test_unauthenticated_request_cannot_access_attachments(): void
    {
        Storage::fake('local');

        [$managerToken, $serviceOrderId] = $this->createOpenOrderScenario();

        $this->flushHeaders();

        $this->postJson('/api/v1/service-orders/'.$serviceOrderId.'/attachments', [
            'attachment_type' => 'diagnostic_image',
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertUnauthorized();

        $this->getJson('/api/v1/service-orders/'.$serviceOrderId.'/attachments')
            ->assertUnauthorized();
    }

    public function test_technician_can_upload_attachments_to_assigned_service_orders(): void
    {
        Storage::fake('local');

        [$managerToken, $serviceOrderId, $companyId, $technicianToken, $technicianMembershipId] = $this->createOpenOrderScenario();

        ServiceOrder::query()->findOrFail($serviceOrderId)
            ->forceFill(['assigned_membership_id' => $technicianMembershipId])
            ->save();

        $this->withToken($technicianToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/attachments', [
                'attachment_type' => 'diagnostic_image',
                'file' => UploadedFile::fake()->image('diag.jpg'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.attachment_type', 'diagnostic_image');
    }

    public function test_tenant_isolation_prevents_cross_company_attachment_access(): void
    {
        Storage::fake('local');

        [$managerTokenA, $serviceOrderIdA] = $this->createOpenOrderScenario('company-a@example.com');
        [$managerTokenB, $serviceOrderIdB] = $this->createOpenOrderScenario('company-b@example.com');

        $attachmentIdA = $this->withToken($managerTokenA)
            ->postJson('/api/v1/service-orders/'.$serviceOrderIdA.'/attachments', [
                'attachment_type' => 'intake_photo',
                'file' => UploadedFile::fake()->image('photo-a.jpg'),
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($managerTokenB)
            ->getJson('/api/v1/service-orders/'.$serviceOrderIdA.'/attachments')
            ->assertNotFound();

        $this->withToken($managerTokenB)
            ->get('/api/v1/service-orders/'.$serviceOrderIdA.'/attachments/'.$attachmentIdA.'/download')
            ->assertNotFound();

        $this->withToken($managerTokenB)
            ->postJson('/api/v1/service-orders/'.$serviceOrderIdA.'/attachments', [
                'attachment_type' => 'other',
                'file' => UploadedFile::fake()->image('inject.jpg'),
            ])
            ->assertNotFound();
    }

    public function test_timeline_event_is_recorded_on_attachment_upload(): void
    {
        Storage::fake('local');

        [$managerToken, $serviceOrderId] = $this->createOpenOrderScenario();

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/attachments', [
                'attachment_type' => 'term',
                'notes' => 'Termo assinado pelo cliente.',
                'file' => UploadedFile::fake()->create('termo.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('service_order_timeline_events', [
            'service_order_id' => $serviceOrderId,
            'type' => 'attachment_added',
        ]);
    }

    /**
     * @return array{string, int, int, string, int}
     */
    protected function createOpenOrderScenario(string $companyEmail = 'attach@example.com'): array
    {
        $company = Company::query()->create([
            'name' => 'Attachment Test Company '.$companyEmail,
            'email' => $companyEmail,
            'is_active' => true,
        ]);

        $managerUser = User::query()->create([
            'name' => 'Manager Attach '.$companyEmail,
            'email' => 'manager.'.$companyEmail,
            'password' => Hash::make('secret'),
        ]);

        $managerMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $managerUser->getKey(),
            'role' => 'manager',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $technicianUser = User::query()->create([
            'name' => 'Tech Attach '.$companyEmail,
            'email' => 'tech.'.$companyEmail,
            'password' => Hash::make('secret'),
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
            'name' => 'Cliente Attach',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Attach',
            'email' => 'contato.attach@example.com',
            'phone' => '(11) 95555-9900',
            'normalized_email' => 'contato.attach@example.com',
            'normalized_phone' => '11955559900',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Smartphone',
            'brand' => 'Samsung',
            'model' => 'Galaxy S22',
            'serial_number' => 'ATTACH-'.$company->getKey(),
        ]);

        $template = IntakeChecklistTemplate::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'category' => 'Smartphone',
            'version' => 1,
            'title' => 'Checklist Smartphone Attach',
            'items' => [
                ['key' => 'charger', 'label' => 'Carregador entregue', 'type' => 'boolean', 'required' => true],
            ],
        ]);

        $managerToken = app(IssueApiToken::class)->handle(
            $managerUser,
            $managerMembership,
            'attach-manager-test',
        )['plain_text_token'];

        $technicianToken = app(IssueApiToken::class)->handle(
            $technicianUser,
            $technicianMembership,
            'attach-tech-test',
        )['plain_text_token'];

        $serviceOrderId = $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts', [
                'customer_id' => $customer->getKey(),
                'customer_contact_id' => $contact->getKey(),
                'equipment_id' => $equipment->getKey(),
                'intake_checklist_template_id' => $template->getKey(),
                'equipment_description' => 'Samsung Galaxy S22 com capa protetora.',
                'reported_problem' => 'Tela piscando.',
                'intake_checklist' => [
                    ['label' => 'Carregador entregue', 'checked' => true],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($managerToken)
            ->postJson('/api/v1/service-orders/drafts/'.$serviceOrderId.'/open')
            ->assertOk();

        return [$managerToken, $serviceOrderId, $company->getKey(), $technicianToken, $technicianMembership->getKey()];
    }
}
