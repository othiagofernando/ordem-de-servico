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

class ServiceOrderTrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_trashed_orders_appear_in_trash_list(): void
    {
        [$token, $serviceOrderId] = $this->createAndTrashOrder();

        $this->withToken($token)
            ->getJson('/api/v1/service-orders/trash')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $serviceOrderId);
    }

    public function test_non_trashed_orders_do_not_appear_in_trash_list(): void
    {
        [$token] = $this->createScenario(ServiceOrder::STATUS_OPEN, trash: false);

        $this->withToken($token)
            ->getJson('/api/v1/service-orders/trash')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_trash_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/service-orders/trash')->assertUnauthorized();
    }

    public function test_trashed_order_can_be_restored_with_correct_password(): void
    {
        [$token, $serviceOrderId] = $this->createAndTrashOrder();

        $this->withToken($token)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/restore', [
                'password' => 'Password@1',
            ])
            ->assertNoContent();

        $this->assertDatabaseHas('service_orders', [
            'id' => $serviceOrderId,
            'deleted_at' => null,
        ]);
    }

    public function test_restore_rejected_with_wrong_password(): void
    {
        [$token, $serviceOrderId] = $this->createAndTrashOrder();

        $this->withToken($token)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/restore', [
                'password' => 'errada',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertSoftDeleted('service_orders', ['id' => $serviceOrderId]);
    }

    public function test_restore_of_non_trashed_order_is_rejected(): void
    {
        [$token, $serviceOrderId] = $this->createScenario(ServiceOrder::STATUS_CANCELLED, trash: false);

        $this->withToken($token)
            ->postJson('/api/v1/service-orders/'.$serviceOrderId.'/restore', [
                'password' => 'Password@1',
            ])
            ->assertUnprocessable();
    }

    public function test_trashed_order_can_be_force_deleted_with_correct_password(): void
    {
        [$token, $serviceOrderId] = $this->createAndTrashOrder();

        $this->withToken($token)
            ->deleteJson('/api/v1/service-orders/'.$serviceOrderId.'/force', [
                'password' => 'Password@1',
            ])
            ->assertNoContent();

        $this->assertDatabaseMissing('service_orders', ['id' => $serviceOrderId]);
    }

    public function test_force_delete_rejected_with_wrong_password(): void
    {
        [$token, $serviceOrderId] = $this->createAndTrashOrder();

        $this->withToken($token)
            ->deleteJson('/api/v1/service-orders/'.$serviceOrderId.'/force', [
                'password' => 'errada',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertSoftDeleted('service_orders', ['id' => $serviceOrderId]);
    }

    public function test_force_delete_of_non_trashed_order_is_rejected(): void
    {
        [$token, $serviceOrderId] = $this->createScenario(ServiceOrder::STATUS_CANCELLED, trash: false);

        $this->withToken($token)
            ->deleteJson('/api/v1/service-orders/'.$serviceOrderId.'/force', [
                'password' => 'Password@1',
            ])
            ->assertUnprocessable();
    }

    public function test_technician_cannot_access_trash(): void
    {
        [$adminToken, , $company, $membership] = $this->createAndTrashOrder(returnAll: true);

        $techUser = User::query()->create([
            'name' => 'Tech Trash Test',
            'email' => 'tech.trash@example.com',
            'password' => Hash::make('Password@1'),
        ]);
        $techMembership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $techUser->getKey(),
            'role' => 'technician',
            'status' => Membership::STATUS_ACTIVE,
        ]);
        $techToken = app(IssueApiToken::class)->handle($techUser, $techMembership, 'tech-trash-test')['plain_text_token'];

        $this->withToken($techToken)
            ->getJson('/api/v1/service-orders/trash')
            ->assertForbidden();
    }

    /** @return array{string, int} */
    private function createAndTrashOrder(bool $returnAll = false): array
    {
        [$token, $serviceOrderId, $company, $membership] = $this->createScenario(ServiceOrder::STATUS_CANCELLED, trash: false);

        $this->withToken($token)
            ->deleteJson('/api/v1/service-orders/'.$serviceOrderId, ['password' => 'Password@1'])
            ->assertNoContent();

        if ($returnAll) {
            return [$token, $serviceOrderId, $company, $membership];
        }

        return [$token, $serviceOrderId];
    }

    /** @return array{string, int, Company, Membership} */
    private function createScenario(string $status, bool $trash): array
    {
        $company = Company::query()->create([
            'name' => 'Empresa Lixeira Test',
            'email' => 'lixeira.'.now()->timestamp.'@example.com',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Manager Lixeira',
            'email' => 'manager.lixeira.'.now()->timestamp.'@example.com',
            'password' => Hash::make('Password@1'),
        ]);

        $membership = Membership::query()->withoutGlobalScopes()->create([
            'company_id' => $company->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'manager',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $token = app(IssueApiToken::class)->handle($user, $membership, 'lixeira-test')['plain_text_token'];

        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'customer_type' => 'pf',
            'name' => 'Cliente Lixeira',
        ]);

        $contact = CustomerContact::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'label' => 'principal',
            'name' => 'Contato Lixeira',
        ]);

        $equipment = Equipment::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'category' => 'Notebook',
            'brand' => 'Lenovo',
            'model' => 'ThinkPad',
            'serial_number' => 'TRASH-'.now()->timestamp,
        ]);

        $serviceOrder = ServiceOrder::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'customer_contact_id' => $contact->getKey(),
            'equipment_id' => $equipment->getKey(),
            'reference' => 'TRH-'.now()->timestamp,
            'title' => 'Ordem Lixeira',
            'status' => $status,
            'reported_problem' => 'Teste lixeira.',
            'opened_at' => now(),
        ]);

        return [$token, $serviceOrder->getKey(), $company, $membership];
    }
}
