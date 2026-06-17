<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerContact;
use App\Domains\Equipments\Models\Equipment;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ServiceOrderDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_order_can_be_deleted_with_correct_password(): void
    {
        [$token, $serviceOrderId] = $this->createTerminalScenario(ServiceOrder::STATUS_CANCELLED);

        $this->withToken($token)
            ->deleteJson('/api/v1/service-orders/'.$serviceOrderId, [
                'password' => 'Password@1',
            ])
            ->assertNoContent();

        $this->assertSoftDeleted('service_orders', ['id' => $serviceOrderId]);
    }

    public function test_no_repair_order_can_be_deleted_with_correct_password(): void
    {
        [$token, $serviceOrderId] = $this->createTerminalScenario(ServiceOrder::STATUS_NO_REPAIR);

        $this->withToken($token)
            ->deleteJson('/api/v1/service-orders/'.$serviceOrderId, [
                'password' => 'Password@1',
            ])
            ->assertNoContent();

        $this->assertSoftDeleted('service_orders', ['id' => $serviceOrderId]);
    }

    public function test_delete_is_rejected_with_wrong_password(): void
    {
        [$token, $serviceOrderId] = $this->createTerminalScenario(ServiceOrder::STATUS_CANCELLED);

        $this->withToken($token)
            ->deleteJson('/api/v1/service-orders/'.$serviceOrderId, [
                'password' => 'senhaerrada',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseHas('service_orders', ['id' => $serviceOrderId]);
    }

    public function test_active_order_cannot_be_deleted(): void
    {
        [$token, $serviceOrderId] = $this->createTerminalScenario(ServiceOrder::STATUS_OPEN);

        $this->withToken($token)
            ->deleteJson('/api/v1/service-orders/'.$serviceOrderId, [
                'password' => 'Password@1',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('service_orders', ['id' => $serviceOrderId]);
    }

    public function test_delivered_order_cannot_be_deleted(): void
    {
        [$token, $serviceOrderId] = $this->createTerminalScenario(ServiceOrder::STATUS_DELIVERED);

        $this->withToken($token)
            ->deleteJson('/api/v1/service-orders/'.$serviceOrderId, [
                'password' => 'Password@1',
            ])
            ->assertUnprocessable();
    }

    public function test_technician_cannot_delete_order(): void
    {
        [$adminToken, $serviceOrderId, $company] = $this->createTerminalScenario(ServiceOrder::STATUS_CANCELLED, returnCompany: true);

        $techUser = User::query()->create([
            'name' => 'Tech Delete Test',
            'email' => 'tech.delete@example.com',
            'password' => Hash::make('Password@1'),
        ]);
        $techMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $techUser->getKey(),
            'role' => 'technician',
            'status' => Membership::STATUS_ACTIVE,
        ]);
        $techToken = app(IssueApiToken::class)->handle($techUser, $techMembership, 'tech-delete-test')['plain_text_token'];

        $this->withToken($techToken)
            ->deleteJson('/api/v1/service-orders/'.$serviceOrderId, [
                'password' => 'Password@1',
            ])
            ->assertForbidden();
    }

    public function test_order_with_warranty_return_cannot_be_deleted(): void
    {
        [$token, $serviceOrderId, $company] = $this->createTerminalScenario(ServiceOrder::STATUS_CANCELLED, returnCompany: true);

        $warrantyCustomer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'customer_type' => 'pf',
            'name' => 'Cliente Garantia',
        ]);
        $warrantyContact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $warrantyCustomer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Garantia',
        ]);
        $warrantyEquipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $warrantyCustomer->getKey(),
            'category' => 'Notebook',
            'brand' => 'HP',
            'model' => 'ProBook',
            'serial_number' => 'WARRANTY-DELETE-TEST',
        ]);

        ServiceOrder::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $warrantyCustomer->getKey(),
            'customer_contact_id' => $warrantyContact->getKey(),
            'equipment_id' => $warrantyEquipment->getKey(),
            'reference' => 'WARRANTY-'.now()->timestamp,
            'title' => 'Ordem de Garantia',
            'status' => ServiceOrder::STATUS_OPEN,
            'reported_problem' => 'Retorno de garantia.',
            'opened_at' => now(),
            'warranty_origin_service_order_id' => $serviceOrderId,
        ]);

        $this->withToken($token)
            ->deleteJson('/api/v1/service-orders/'.$serviceOrderId, [
                'password' => 'Password@1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['warranty']);
    }

    public function test_unauthenticated_delete_is_rejected(): void
    {
        [$token, $serviceOrderId] = $this->createTerminalScenario(ServiceOrder::STATUS_CANCELLED);

        $this->deleteJson('/api/v1/service-orders/'.$serviceOrderId, [
            'password' => 'Password@1',
        ])->assertUnauthorized();
    }

    /** @return array{string, int}|array{string, int, Company} */
    private function createTerminalScenario(string $status, bool $returnCompany = false): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa Delete Test',
            'email' => 'delete.test.'.now()->timestamp.'@example.com',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Manager Delete Test',
            'email' => 'manager.delete.'.now()->timestamp.'@example.com',
            'password' => Hash::make('Password@1'),
        ]);

        $membership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'manager',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $token = app(IssueApiToken::class)->handle($user, $membership, 'delete-test')['plain_text_token'];

        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'customer_type' => 'pf',
            'name' => 'Cliente Delete Test',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Delete Test',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Dell',
            'model' => 'Latitude',
            'serial_number' => 'DELETE-TEST-'.now()->timestamp,
        ]);

        $serviceOrder = ServiceOrder::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'customer_contact_id' => $contact->getKey(),
            'equipment_id' => $equipment->getKey(),
            'reference' => 'DEL-'.now()->timestamp,
            'title' => 'Ordem para Exclusão',
            'status' => $status,
            'reported_problem' => 'Problema de teste para exclusão.',
            'opened_at' => now(),
        ]);

        if ($returnCompany) {
            return [$token, $serviceOrder->getKey(), $company];
        }

        return [$token, $serviceOrder->getKey()];
    }
}
