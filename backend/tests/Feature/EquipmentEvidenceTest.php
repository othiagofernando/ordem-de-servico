<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Customers\Models\Customer;
use App\Domains\Equipments\Models\Equipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EquipmentEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_upload_and_list_evidence(): void
    {
        Storage::fake('local');

        [$token, $membership, $equipment] = $this->createEvidenceAccess();

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->withToken($token)
            ->postJson('/api/v1/equipments/'.$equipment->getKey().'/evidences', [
                'file' => $file,
                'caption' => 'Foto frontal do equipamento',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.equipment_id', $equipment->getKey())
            ->assertJsonPath('data.file_name', 'photo.jpg')
            ->assertJsonPath('data.caption', 'Foto frontal do equipamento')
            ->assertJsonPath('data.uploaded_by_membership_id', $membership->getKey());

        $this->assertDatabaseHas('equipment_evidences', [
            'equipment_id' => $equipment->getKey(),
            'company_id' => $membership->company_id,
            'caption' => 'Foto frontal do equipamento',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/equipments/'.$equipment->getKey().'/evidences')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.file_name', 'photo.jpg');
    }

    public function test_technician_can_view_but_not_upload_evidence(): void
    {
        Storage::fake('local');

        [$adminToken, , $equipment] = $this->createEvidenceAccess();

        // Upload evidence as admin first
        $this->withToken($adminToken)
            ->postJson('/api/v1/equipments/'.$equipment->getKey().'/evidences', [
                'file' => UploadedFile::fake()->image('existing.jpg'),
                'caption' => 'Existente',
            ])
            ->assertCreated();

        // Create a membership that has view_any but not manage (custom permissions)
        [$viewerToken] = $this->createEvidenceAccess(
            email: 'viewer-evidence@example.com',
            role: 'technician',
            customPermissions: ['equipments.view_any'],
            companyName: 'Same Company For Viewer',
        );

        // But actually we need a viewer for the SAME equipment's company,
        // so let's create a membership within the same company using the admin's company
        [$adminToken2, $adminMembership2, $equipment2] = $this->createEvidenceAccess(
            email: 'admin2-evidence@example.com',
            companyName: 'Company With Viewer',
        );

        // Create a viewer membership in the same company
        $viewerUser = User::query()->create([
            'name' => 'Viewer User',
            'email' => 'viewer-only@example.com',
            'password' => Hash::make('secret-pass'),
        ]);

        $viewerMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $adminMembership2->company_id,
            'user_id' => $viewerUser->getKey(),
            'role' => 'technician',
            'status' => Membership::STATUS_ACTIVE,
            'permissions' => ['equipments.view_any'],
        ]);

        $viewerToken2 = app(IssueApiToken::class)->handle($viewerUser, $viewerMembership, 'viewer-test')['plain_text_token'];

        // Viewer can list evidences
        $this->withToken($viewerToken2)
            ->getJson('/api/v1/equipments/'.$equipment2->getKey().'/evidences')
            ->assertOk();

        // Viewer cannot upload
        $this->withToken($viewerToken2)
            ->postJson('/api/v1/equipments/'.$equipment2->getKey().'/evidences', [
                'file' => UploadedFile::fake()->image('upload.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_evidence_is_isolated_by_company(): void
    {
        Storage::fake('local');

        [$token, , $equipment] = $this->createEvidenceAccess();
        [$otherToken] = $this->createEvidenceAccess(
            email: 'other-evidence@example.com',
            companyName: 'Other Evidence Co',
        );

        // Other company administrator cannot list evidences for first company's equipment (404)
        $this->withToken($otherToken)
            ->getJson('/api/v1/equipments/'.$equipment->getKey().'/evidences')
            ->assertNotFound();

        // Other company administrator cannot upload to first company's equipment (404)
        $this->withToken($otherToken)
            ->postJson('/api/v1/equipments/'.$equipment->getKey().'/evidences', [
                'file' => UploadedFile::fake()->image('hack.jpg'),
            ])
            ->assertNotFound();
    }

    public function test_show_endpoint_includes_evidences_and_service_orders_history(): void
    {
        Storage::fake('local');

        [$token, , $equipment] = $this->createEvidenceAccess();

        $this->withToken($token)
            ->postJson('/api/v1/equipments/'.$equipment->getKey().'/evidences', [
                'file' => UploadedFile::fake()->image('evidence.png'),
                'caption' => 'Evidência inicial',
            ])
            ->assertCreated();

        $this->withToken($token)
            ->getJson('/api/v1/equipments/'.$equipment->getKey())
            ->assertOk()
            ->assertJsonCount(1, 'data.evidences')
            ->assertJsonPath('data.evidences.0.caption', 'Evidência inicial')
            ->assertJsonStructure([
                'data' => [
                    'evidences' => [['id', 'file_name', 'mime_type', 'file_size_bytes', 'caption', 'created_at']],
                    'service_orders_history',
                ],
            ]);
    }

    /**
     * @return array{string, Membership, Equipment}
     */
    protected function createEvidenceAccess(
        string $email = 'evidence@example.com',
        string $role = 'administrator',
        array $customPermissions = [],
        string $companyName = 'Acme Evidence',
    ): array {
        $user = User::query()->create([
            'name' => 'Evidence User',
            'email' => $email,
            'password' => Hash::make('secret-pass'),
        ]);

        $company = Company::query()->create([
            'name' => $companyName,
            'email' => $email,
            'is_active' => true,
        ]);

        $membershipData = [
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
            'status' => Membership::STATUS_ACTIVE,
        ];

        if (! empty($customPermissions)) {
            $membershipData['permissions'] = $customPermissions;
        }

        $membership = Membership::query()->withoutGlobalScopes()->create($membershipData);

        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'customer_type' => 'pf',
            'name' => 'Cliente Evidência',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Dell',
            'model' => 'Latitude 5420',
        ]);

        $token = app(IssueApiToken::class)->handle($user, $membership, 'evidence-test')['plain_text_token'];

        return [$token, $membership, $equipment];
    }
}
