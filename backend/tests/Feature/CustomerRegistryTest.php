<?php

namespace Tests\Feature;

use App\Domains\Auth\Actions\IssueApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_view_update_and_search_tenant_scoped_customers(): void
    {
        [$token] = $this->createAttendantAccess();

        $create = $this->withToken($token)->postJson('/api/v1/customers', [
            'customer_type' => 'pf',
            'name' => 'Maria Silva',
            'document' => '12345678900',
            'contacts' => [
                [
                    'label' => 'principal',
                    'email' => 'maria@example.com',
                    'phone' => '(11) 99999-1234',
                ],
            ],
            'addresses' => [
                [
                    'label' => 'casa',
                    'line_1' => 'Rua A, 100',
                    'city' => 'Sao Paulo',
                    'state' => 'SP',
                ],
            ],
        ]);

        $customerId = $create->assertCreated()
            ->assertJsonPath('data.name', 'Maria Silva')
            ->assertJsonCount(1, 'data.contacts')
            ->assertJsonCount(1, 'data.addresses')
            ->json('data.id');

        $this->withToken($token)
            ->getJson('/api/v1/customers?q=Maria')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $customerId);

        $this->withToken($token)
            ->putJson('/api/v1/customers/'.$customerId, [
                'customer_type' => 'pf',
                'name' => 'Maria Souza',
                'document' => '12345678900',
                'contacts' => [
                    [
                        'label' => 'principal',
                        'email' => 'maria@example.com',
                        'phone' => '11988882222',
                    ],
                ],
                'addresses' => [
                    [
                        'label' => 'casa',
                        'line_1' => 'Rua B, 200',
                        'city' => 'Sao Paulo',
                        'state' => 'SP',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Maria Souza')
            ->assertJsonPath('data.contacts.0.phone', '11988882222');

        $this->withToken($token)
            ->getJson('/api/v1/customers/'.$customerId)
            ->assertOk()
            ->assertJsonPath('data.name', 'Maria Souza');
    }

    public function test_customer_document_is_unique_per_company_but_not_global(): void
    {
        [$token] = $this->createAttendantAccess();
        [$otherToken] = $this->createAttendantAccess('other@example.com', 'Other Co');

        $payload = [
            'customer_type' => 'pj',
            'name' => 'Acme LTDA',
            'document' => '99887766000155',
            'contacts' => [
                ['email' => 'contato@acme.test'],
            ],
        ];

        $this->withToken($token)
            ->postJson('/api/v1/customers', $payload)
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/customers', [
                ...$payload,
                'name' => 'Acme Duplicada',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document']);

        $this->withToken($otherToken)
            ->postJson('/api/v1/customers', [
                ...$payload,
                'name' => 'Acme Outra Empresa',
            ])
            ->assertCreated();
    }

    public function test_duplicate_warning_requires_explicit_confirmation_and_is_tenant_scoped(): void
    {
        [$token] = $this->createAttendantAccess();
        [$otherToken] = $this->createAttendantAccess('isolated@example.com', 'Isolated Co');

        $this->withToken($token)->postJson('/api/v1/customers', [
            'customer_type' => 'pf',
            'name' => 'Cliente Original',
            'contacts' => [
                ['email' => 'dup@example.com', 'phone' => '(11) 90000-0001'],
            ],
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/customers', [
            'customer_type' => 'pf',
            'name' => 'Possivel Duplicado',
            'contacts' => [
                ['email' => 'dup@example.com', 'phone' => '(11) 90000-0002'],
            ],
        ])->assertConflict()
            ->assertJsonCount(1, 'duplicate_candidates');

        $this->withToken($token)->postJson('/api/v1/customers', [
            'customer_type' => 'pf',
            'name' => 'Possivel Duplicado',
            'confirm_duplicates' => true,
            'contacts' => [
                ['email' => 'dup@example.com', 'phone' => '(11) 90000-0002'],
            ],
        ])->assertCreated();

        $this->withToken($otherToken)->postJson('/api/v1/customers', [
            'customer_type' => 'pf',
            'name' => 'Outro Tenant',
            'contacts' => [
                ['email' => 'dup@example.com', 'phone' => '(11) 90000-0001'],
            ],
        ])->assertCreated();
    }

    public function test_customers_are_isolated_by_company(): void
    {
        [$token] = $this->createAttendantAccess();
        [$otherToken] = $this->createAttendantAccess('other2@example.com', 'Other 2');

        $customerId = $this->withToken($token)->postJson('/api/v1/customers', [
            'customer_type' => 'pf',
            'name' => 'Tenant A Customer',
            'contacts' => [
                ['email' => 'tenanta@example.com'],
            ],
        ])->json('data.id');

        $this->withToken($otherToken)
            ->getJson('/api/v1/customers/'.$customerId)
            ->assertNotFound();
    }

    public function test_customer_index_excludes_other_tenant_records_and_meta_totals(): void
    {
        [$token] = $this->createAttendantAccess();
        [$otherToken] = $this->createAttendantAccess('other3@example.com', 'Other 3');

        $this->withToken($token)->postJson('/api/v1/customers', [
            'customer_type' => 'pf',
            'name' => 'Cliente Tenant A',
            'contacts' => [
                ['email' => 'a@example.com'],
            ],
        ])->assertCreated();

        $this->withToken($otherToken)->postJson('/api/v1/customers', [
            'customer_type' => 'pf',
            'name' => 'Cliente Tenant B',
            'contacts' => [
                ['email' => 'b@example.com'],
            ],
        ])->assertCreated();

        $response = $this->withToken($token)
            ->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 1);

        $this->assertSame(['Cliente Tenant A'], collect($response->json('data'))->pluck('name')->all());
    }

    /**
     * @return array{string, Membership}
     */
    protected function createAttendantAccess(
        string $email = 'attendant@example.com',
        string $companyName = 'Acme Assistencia',
    ): array {
        $user = User::query()->create([
            'name' => 'Attendant',
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
            'role' => 'attendant',
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $token = app(IssueApiToken::class)->handle($user, $membership, 'customer-test')['plain_text_token'];

        return [$token, $membership];
    }
}
