<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Customers\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EquipmentRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_view_update_and_search_tenant_scoped_equipment(): void
    {
        [$token, $membership, $customer] = $this->createEquipmentAccess();

        $create = $this->withToken($token)->postJson('/api/v1/equipments', [
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Dell',
            'model' => 'Latitude 5420',
            'serial_number' => 'SN-1000',
            'accessories' => 'Carregador e bolsa',
            'notes' => 'Equipamento corporativo',
        ]);

        $equipmentId = $create->assertCreated()
            ->assertJsonPath('data.customer_id', $customer->getKey())
            ->assertJsonPath('data.serial_number', 'SN-1000')
            ->json('data.id');

        $this->withToken($token)
            ->getJson('/api/v1/equipments?q=Latitude')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $equipmentId);

        $this->withToken($token)
            ->putJson('/api/v1/equipments/'.$equipmentId, [
                'customer_id' => $customer->getKey(),
                'category' => 'Notebook',
                'brand' => 'Dell',
                'model' => 'Latitude 5430',
                'serial_number' => 'SN-1000',
                'accessories' => 'Carregador',
                'notes' => 'Atualizado',
            ])
            ->assertOk()
            ->assertJsonPath('data.model', 'Latitude 5430');

        $this->withToken($token)
            ->getJson('/api/v1/equipments/'.$equipmentId)
            ->assertOk()
            ->assertJsonPath('data.customer_name', $customer->name);
    }

    public function test_serial_number_is_unique_per_company_without_override(): void
    {
        [$token, , $customer] = $this->createEquipmentAccess();

        $payload = [
            'customer_id' => $customer->getKey(),
            'category' => 'Impressora',
            'brand' => 'HP',
            'model' => 'LaserJet',
            'serial_number' => 'SERIAL-XYZ',
        ];

        $this->withToken($token)
            ->postJson('/api/v1/equipments', $payload)
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/equipments', [
                ...$payload,
                'model' => 'LaserJet 2',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['serial_number']);
    }

    public function test_privileged_membership_can_override_serial_uniqueness_and_it_is_logged(): void
    {
        [$adminToken, $adminMembership, $customer] = $this->createEquipmentAccess();
        [$attendantToken, , $attendantCustomer] = $this->createEquipmentAccess(
            email: 'attendant-eq@example.com',
            role: 'attendant',
            companyName: 'Second Company',
        );

        $this->withToken($adminToken)
            ->postJson('/api/v1/equipments', [
                'customer_id' => $customer->getKey(),
                'category' => 'Celular',
                'serial_number' => 'IMEI-1',
            ])
            ->assertCreated();

        $this->withToken($adminToken)
            ->postJson('/api/v1/equipments', [
                'customer_id' => $customer->getKey(),
                'category' => 'Celular',
                'serial_number' => 'IMEI-1',
                'override_serial_uniqueness' => true,
                'override_reason' => 'Serial reaproveitado pelo fabricante.',
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'data.serial_overrides');

        $this->assertDatabaseHas('equipment_serial_overrides', [
            'company_id' => $adminMembership->company_id,
            'serial_number' => 'IMEI-1',
            'reason' => 'Serial reaproveitado pelo fabricante.',
        ]);

        $this->withToken($attendantToken)
            ->postJson('/api/v1/equipments', [
                'customer_id' => $attendantCustomer->getKey(),
                'category' => 'Tablet',
                'serial_number' => 'TAB-1',
            ])
            ->assertCreated();

        $this->withToken($attendantToken)
            ->postJson('/api/v1/equipments', [
                'customer_id' => $attendantCustomer->getKey(),
                'category' => 'Tablet',
                'serial_number' => 'TAB-1',
                'override_serial_uniqueness' => true,
                'override_reason' => 'Tentativa sem permissao.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['serial_number']);
    }

    public function test_equipment_is_isolated_by_company(): void
    {
        [$token, , $customer] = $this->createEquipmentAccess();
        [$otherToken] = $this->createEquipmentAccess(email: 'other-eq@example.com', companyName: 'Other Equip Co');

        $equipmentId = $this->withToken($token)
            ->postJson('/api/v1/equipments', [
                'customer_id' => $customer->getKey(),
                'category' => 'Monitor',
            ])
            ->json('data.id');

        $this->withToken($otherToken)
            ->getJson('/api/v1/equipments/'.$equipmentId)
            ->assertNotFound();
    }

    /**
     * @return array{string, Membership, Customer}
     */
    protected function createEquipmentAccess(
        string $email = 'equipment@example.com',
        string $role = 'administrator',
        string $companyName = 'Acme Assistencia',
    ): array {
        $user = User::query()->create([
            'name' => 'Equipment User',
            'email' => $email,
            'password' => Hash::make('secret-pass'),
        ]);

        $company = Company::query()->create([
            'name' => $companyName,
            'email' => $email,
            'is_active' => true,
        ]);

        $membership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'customer_type' => 'pf',
            'name' => 'Cliente Equipamento',
        ]);

        $token = app(IssueApiToken::class)->handle($user, $membership, 'equipment-test')['plain_text_token'];

        return [$token, $membership, $customer];
    }
}
